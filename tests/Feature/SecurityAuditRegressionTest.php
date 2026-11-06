<?php

namespace Tests\Feature;

use App\Models\Diocese;
use App\Models\District;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\JurisdictionAssignment;
use App\Models\ManagedDocument;
use App\Models\Parish;
use App\Models\Permission;
use App\Models\ReportExport;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Services\DocumentStorageService;
use App\Services\ProductionReadinessService;
use App\Services\ReportExportService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Database\Seeders\PhaseNineToFourteenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class, PhaseNineToFourteenSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $school = School::create(['diocese_id' => Diocese::first()->id, 'parish_id' => Parish::first()->id, 'district_id' => District::first()->id, 'official_name' => 'Audit School', 'diocesan_code' => 'AUDIT-1', 'school_level' => 'primary', 'ownership' => 'diocesan', 'operational_status' => 'active']);
        $period = ReportingPeriod::create(['title' => 'Audit', 'type' => 'annual_census', 'year' => 2026, 'status' => 'open', 'opening_date' => '2026-01-01', 'closing_date' => '2026-12-31', 'created_by' => $admin->id]);
        $return = SchoolReturn::create(['school_id' => $school->id, 'reporting_period_id' => $period->id, 'status' => 'draft', 'started_by' => $admin->id]);

        return [$admin, $school, $return];
    }

    public function test_read_only_permission_cannot_write(): void
    {
        [$admin,$school,$return] = $this->fixtures();
        $role = Role::create(['slug' => 'audit-read-only', 'name' => 'Audit read only']);
        $role->permissions()->sync(Permission::whereIn('slug', ['returns.view', 'schools.view.assigned'])->pluck('id'));
        $reader = User::factory()->create();
        $reader->roles()->attach($role);
        JurisdictionAssignment::create(['user_id' => $reader->id, 'parish_id' => $school->parish_id, 'assignment_type' => 'parish_coordinator', 'effective_from' => '2020-01-01', 'is_active' => true]);
        $this->assertFalse($reader->hasPermission('returns.manage.assigned'));
        $this->actingAs($reader)->post(route('returns.form.save', [$return, 'enrolment']), ['data' => ['classes' => ['year_1' => ['boys' => 9, 'girls' => 8]]]])->assertForbidden();
        $this->assertNull($return->fresh()->statistical_form);
    }

    public function test_reviewer_cannot_transition_a_school_they_cannot_view(): void
    {
        [$admin,$school,$return] = $this->fixtures();
        $return->update(['status' => 'submitted']);
        $reviewer = User::factory()->create();
        $reviewer->roles()->attach(tap(Role::firstOrCreate(['slug' => 'parish-education-coordinator'], ['name' => 'Scoped test reviewer']), fn ($role) => $role->permissions()->sync(Permission::whereIn('slug', ['schools.view.assigned', 'returns.view', 'returns.review'])->pluck('id'))));
        $this->assertFalse(Gate::forUser($reviewer)->allows('view', $school));
        $this->actingAs($reviewer)->post(route('returns.transition', $return), ['action' => 'return_for_correction', 'reason' => 'Audit reproduction'])->assertForbidden();
        $this->assertSame('submitted', $return->fresh()->status);
    }

    public function test_documents_cannot_be_read_outside_school_scope(): void
    {
        [$admin,$school,$return] = $this->fixtures();
        Storage::fake('local');
        $this->actingAs($admin)->post(route('documents.store'), ['document_category_id' => DocumentCategory::firstOrFail()->id, 'school_id' => $school->id, 'title' => 'Private audit school file', 'sensitivity' => 'confidential', 'file' => UploadedFile::fake()->create('audit.pdf', 12, 'application/pdf')])->assertRedirect();
        $reader = User::factory()->create();
        $reader->roles()->attach(Role::where('slug', 'headteacher')->firstOrFail());
        $this->assertFalse(Gate::forUser($reader)->allows('view', $school));
        $document = ManagedDocument::firstOrFail();
        $this->actingAs($reader)->get(route('documents.index'))->assertOk()->assertViewHas('documents', fn ($documents) => ! $documents->contains('id', $document->id));
        $this->get(route('documents.download', $document))->assertForbidden();
    }

    public function test_replacement_database_failure_preserves_current_document_and_cleans_file(): void
    {
        [$admin] = $this->fixtures();
        Storage::fake('local');
        $service = app(DocumentStorageService::class);
        $document = $service->store(['document_category_id' => DocumentCategory::first()->id, 'title' => 'Original', 'sensitivity' => 'internal'], UploadedFile::fake()->create('original.pdf', 1, 'application/pdf'), $admin);
        $original = $document->currentVersion->id;
        Event::listen('eloquent.creating: '.DocumentVersion::class, function () {
            throw new \RuntimeException('Injected database failure');
        });
        try {
            $service->attachVersion($document, UploadedFile::fake()->create('replacement.pdf', 1, 'application/pdf'), $admin);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected database failure', $e->getMessage());
        }
        $this->assertSame($original, $document->fresh()->currentVersion->id);
        $this->assertCount(1, Storage::disk('local')->allFiles('mdsiss-documents'));
    }

    public function test_real_exports_are_downloadable_and_private_in_all_formats(): void
    {
        [$admin,$school] = $this->fixtures();
        Storage::fake('local');
        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $this->actingAs($admin)->post(route('reports.export'), ['report_type' => 'school registry', 'format' => $format])->assertSessionHasNoErrors()->assertRedirect(route('reports.index'));
            $export = ReportExport::latest('id')->first();
            $this->assertSame('generated', $export->status);
            $bytes = Storage::disk('local')->get($export->path);
            if ($format === 'csv') {
                $this->assertStringContainsString($school->official_name, $bytes);
            }
            if ($format === 'xlsx') {
                $this->assertStringStartsWith('PK', $bytes);
            }
            if ($format === 'pdf') {
                $this->assertStringStartsWith('%PDF-', $bytes);
            }
            $this->get(route('reports.download', $export))->assertOk();
            $other = User::factory()->create();
            $other->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->first());
            $this->actingAs($other)->get(route('reports.download', $export))->assertForbidden();
        }
    }

    public function test_scoped_exports_do_not_include_another_school(): void
    {
        [$admin,$school] = $this->fixtures();
        Storage::fake('local');
        $role = Role::create(['slug' => 'audit-exporter', 'name' => 'Scoped exporter']);
        $role->permissions()->sync(Permission::whereIn('slug', ['reports.view', 'reports.export', 'schools.view.assigned'])->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $export = app(ReportExportService::class)->generate($user, 'school registry', 'csv');
        $this->assertStringNotContainsString($school->official_name, Storage::disk('local')->get($export->path));
    }

    public function test_reseeding_does_not_reset_an_existing_admin_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@mdsiss.test')->firstOrFail();
        $admin->update(['password' => 'Changed-secure-password-2026']);
        $this->seed(DatabaseSeeder::class);
        $this->assertTrue(Hash::check('Changed-secure-password-2026', $admin->fresh()->password));
    }

    public function test_document_upload_options_use_school_official_names(): void
    {
        [$admin,$school] = $this->fixtures();
        $this->actingAs($admin)->get(route('documents.create'))->assertOk()->assertSee($school->official_name)->assertViewHas('schools', fn ($schools) => $schools->contains('id', $school->id));
    }

    public function test_school_document_visibility_respects_sensitivity_and_scope(): void
    {
        [$admin,$school] = $this->fixtures();
        Storage::fake('local');
        $service = app(DocumentStorageService::class);
        $internal = $service->store(['document_category_id' => DocumentCategory::first()->id, 'school_id' => $school->id, 'title' => 'Internal', 'sensitivity' => 'internal'], UploadedFile::fake()->create('internal.pdf', 1, 'application/pdf'), $admin);
        $confidential = $service->store(['document_category_id' => DocumentCategory::first()->id, 'school_id' => $school->id, 'title' => 'Confidential', 'sensitivity' => 'confidential'], UploadedFile::fake()->create('secret.pdf', 1, 'application/pdf'), $admin);
        $reader = User::factory()->create(['school_id' => $school->id]);
        $reader->roles()->attach(Role::where('slug', 'headteacher')->firstOrFail());
        $this->actingAs($reader)->get(route('documents.download', $internal))->assertOk();
        $this->get(route('documents.download', $confidential))->assertForbidden();
    }

    public function test_scoped_document_managers_cannot_upload_to_another_school(): void
    {
        [$admin,$school] = $this->fixtures();
        Storage::fake('local');
        $role = Role::create(['slug' => 'audit-scoped-document-manager', 'name' => 'Scoped document manager']);
        $role->permissions()->sync(Permission::whereIn('slug', ['documents.view', 'documents.manage', 'schools.view.assigned'])->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $this->actingAs($user)->post(route('documents.store'), ['document_category_id' => DocumentCategory::first()->id, 'school_id' => $school->id, 'title' => 'Foreign upload', 'sensitivity' => 'internal', 'file' => UploadedFile::fake()->create('file.pdf', 1, 'application/pdf')])->assertForbidden();
        $this->assertDatabaseCount('managed_documents', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_csv_values_cannot_be_interpreted_as_formulas(): void
    {
        [$admin,$school] = $this->fixtures();
        Storage::fake('local');
        $school->update(['official_name' => '=HYPERLINK("https://example.invalid")']);
        $export = app(ReportExportService::class)->generate($admin, 'school registry', 'csv');
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, Storage::disk('local')->get($export->path));
        rewind($stream);
        fgetcsv($stream, 0, ',', '"', '');
        $row = fgetcsv($stream, 0, ',', '"', '');
        fclose($stream);
        $this->assertStringStartsWith("'=", $row[0]);
    }

    public function test_production_seeding_cannot_create_the_demo_administrator(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertExitCode(0);
        $this->assertDatabaseMissing('users', ['email' => 'admin@mdsiss.test']);
    }

    public function test_readiness_rejects_missing_and_stale_dependency_evidence(): void
    {
        [$admin] = $this->fixtures();
        $admin->update(['password' => 'Unique-strong-password-2026']);
        Storage::fake('local');
        $this->app->instance('env', 'production');
        config(['app.debug' => false, 'app.url' => 'https://schools.example.org', 'session.secure' => true, 'session.http_only' => true, 'mail.default' => 'smtp', 'mdsiss.backup_restore_verified_at' => now()->toIso8601String(), 'mdsiss.backup_restore_evidence' => 'Restore drill ticket TEST-01']);
        $service = app(ProductionReadinessService::class);
        $this->assertSame('not production ready', $service->snapshotPayload()['classification']);
        Storage::disk('local')->put('security/dependency-audit.json', json_encode(['clean' => true, 'checked_at' => time() - 90000, 'lock_hash' => hash_file('sha256', base_path('composer.lock'))]));
        $this->assertSame('not production ready', $service->snapshotPayload()['classification']);
        Storage::disk('local')->put('security/dependency-audit.json', json_encode(['clean' => true, 'checked_at' => time(), 'lock_hash' => hash_file('sha256', base_path('composer.lock'))]));
        $this->assertSame('configuration checks passed', $service->snapshotPayload()['classification']);
        config(['mdsiss.backup_restore_verified_at' => null]);
        $this->assertSame('not production ready', $service->snapshotPayload()['classification']);
    }
}
