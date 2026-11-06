<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\DocumentCategory;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Services\DocumentStorageService;
use App\Services\ReportExportService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Database\Seeders\PhaseNineToFourteenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $school = School::create(['official_name' => 'Official school', 'ministry_registration_number' => 'OFFICIAL-MOE-1', 'emis_number' => 'OFFICIAL-EMIS-1', 'diocesan_code' => 'INTEGRITY-1', 'diocese_id' => Parish::first()->diocese_id, 'parish_id' => Parish::first()->id, 'district_id' => District::first()->id, 'school_level' => 'primary', 'ownership' => 'other', 'operational_status' => 'active']);
        $head = User::factory()->create(['school_id' => $school->id, 'password' => 'Original!Password2026']);
        $head->roles()->attach(Role::where('slug', Role::HEADTEACHER)->firstOrFail());

        return [$admin, $head, $school];
    }

    private function accountPayload(User $head, School $school): array
    {
        return ['first_name' => 'School', 'last_name' => 'Headteacher', 'email' => $head->email, 'school_id' => $school->id, 'status' => 'active', 'role_ids' => [Role::where('slug', Role::HEADTEACHER)->firstOrFail()->id], 'password' => '', 'password_confirmation' => ''];
    }

    public function test_school_reassignment_updates_username_and_revokes_old_school_session(): void
    {
        [$admin,$head,$school] = $this->context();
        $next = $school->replicate();
        $next->official_name = 'New assigned school';
        $next->diocesan_code = 'INTEGRITY-2';
        $next->ministry_registration_number = 'OFFICIAL-MOE-2';
        $next->emis_number = 'OFFICIAL-EMIS-2';
        $next->save();
        $this->actingAs($admin)->put(route('administration.users.update', $head), $this->accountPayload($head, $next))->assertSessionHasNoErrors();
        $this->assertSame('OFFICIAL-MOE-2', $head->fresh()->username);
        $this->assertSame(1, $head->fresh()->session_version);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['username' => 'OFFICIAL-MOE-1', 'password' => 'Original!Password2026'])->assertSessionHasErrors('username');
        $this->post(route('login.store'), ['username' => 'OFFICIAL-MOE-2', 'password' => 'Original!Password2026'])->assertSessionHasNoErrors();
    }

    public function test_revocation_works_with_non_database_sessions(): void
    {
        [$admin,$head,$school] = $this->context();
        config(['session.driver' => 'array']);
        $this->actingAs($admin)->put(route('administration.users.update', $head), array_replace($this->accountPayload($head, $school), ['password' => 'Replacement!Password2026', 'password_confirmation' => 'Replacement!Password2026']))->assertSessionHasNoErrors();
        $this->actingAs($head->fresh())->withSession(['auth_session_version' => [$head->id => 0]])->get(route('representative.profile'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post(route('login.store'), ['username' => $head->fresh()->username, 'password' => 'Replacement!Password2026'])->assertSessionHasNoErrors();
        $this->get(route('representative.profile'))->assertOk();
    }

    public function test_headteacher_cannot_change_official_school_identifiers(): void
    {
        [$admin,$head,$school] = $this->context();
        $payload = ['parish_id' => $school->parish_id, 'district_id' => $school->district_id, 'school_level' => 'primary', 'funding_classification' => 'Government', 'gender_classification' => 'Mixed', 'boarding_status' => 'Day', 'ministry_registration_number' => 'FORGED-MOE', 'emis_number' => 'FORGED-EMIS'];
        $this->actingAs($head)->put(route('representative.profile.update'), $payload)->assertSessionHasErrors(['ministry_registration_number', 'emis_number']);
        $this->assertSame('OFFICIAL-MOE-1', $school->fresh()->ministry_registration_number);
        $this->assertSame('OFFICIAL-MOE-1', $head->fresh()->username);
        $this->actingAs($admin)->put(route('schools.update', $school), ['official_name' => $school->official_name, 'parish_id' => $school->parish_id, 'school_level' => 'primary', 'ministry_registration_number' => 'CORRECTED-MOE', 'emis_number' => 'CORRECTED-EMIS'])->assertSessionHasNoErrors();
        $this->assertSame('CORRECTED-MOE', $head->fresh()->username);
    }

    public function test_fresh_install_seeder_does_not_skip_login_username_generation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@mdsiss.test')->firstOrFail();
        $this->assertSame('ADMIN', $admin->username);
    }

    public function test_malformed_announcement_preview_token_is_a_validation_error(): void
    {
        [$admin] = $this->context();
        $this->actingAs($admin)->post(route('announcements.publish'), ['preview_token' => ['invalid']])->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_statistical_export_includes_approved_returns_and_identifies_their_review_status(): void
    {
        [$admin,$head,$school] = $this->context();
        $period = ReportingPeriod::create(['title' => 'Export period', 'type' => 'annual_census', 'year' => 2026, 'status' => 'open', 'opening_date' => now()->subDay(), 'closing_date' => now()->addDay(), 'created_by' => $admin->id]);
        $return = SchoolReturn::create(['school_id' => $school->id, 'reporting_period_id' => $period->id, 'status' => 'approved', 'approved_at' => now(), 'submitted_at' => now(), 'statistical_form' => ['enrolment' => ['classes' => ['year_1' => ['boys' => 12, 'girls' => 13]]]]]);
        Storage::fake('local');
        $export = app(ReportExportService::class)->generate($admin, 'enrolment', 'csv');
        $csv = Storage::disk('local')->get($export->path);
        $this->assertStringContainsString('Approved', $csv);
        $this->assertStringContainsString('Review status', $csv);
        $this->assertStringContainsString($school->official_name, $csv);
        $return->update(['status' => 'returned_for_correction']);
        $next = app(ReportExportService::class)->generate($admin, 'enrolment', 'csv');
        $this->assertStringNotContainsString($school->official_name, Storage::disk('local')->get($next->path));
    }

    public function test_missing_private_document_file_returns_not_found_instead_of_server_error(): void
    {
        [$admin,$head,$school] = $this->context();
        $this->seed(PhaseNineToFourteenSeeder::class);
        Storage::fake('local');
        $document = app(DocumentStorageService::class)->store(['title' => 'Missing stored file', 'school_id' => $school->id, 'sensitivity' => 'internal', 'document_category_id' => DocumentCategory::firstOrFail()->id], UploadedFile::fake()->create('missing.pdf', 1, 'application/pdf'), $admin);
        Storage::disk('local')->delete($document->currentVersion->path);
        $this->actingAs($admin)->get(route('documents.download', $document))->assertNotFound();
    }
}
