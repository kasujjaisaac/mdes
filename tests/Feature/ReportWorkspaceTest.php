<?php

namespace Tests\Feature;

use App\Actions\TransitionSchoolReturn;
use App\Models\Announcement;
use App\Models\AuditEvent;
use App\Models\District;
use App\Models\HelpdeskTicket;
use App\Models\NotificationRecord;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\SubmissionVersion;
use App\Models\User;
use App\Services\ReportDataService;
use App\Services\ReportExportService;
use App\Services\ReportRenderer;
use App\Services\SubmissionVersionService;
use App\Support\Reports\ReportCatalog;
use App\Support\StatisticalForm;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $p = Parish::first();
        $s = School::create(['official_name' => 'Report School', 'ministry_registration_number' => 'REPORT-001', 'emis_number' => '00123', 'diocesan_code' => 'R-01', 'diocese_id' => $p->diocese_id, 'parish_id' => $p->id, 'district_id' => District::first()->id, 'school_level' => 'primary', 'funding_classification' => 'Private', 'gender_classification' => 'Mixed', 'boarding_status' => 'Day', 'ownership' => 'other', 'operational_status' => 'active']);
        $head = User::factory()->create(['school_id' => $s->id]);
        $head->roles()->attach(Role::where('slug', Role::HEADTEACHER)->first());
        $s->leadershipHistories()->create(['position' => 'headteacher', 'person_name' => 'Report Teacher', 'telephone' => '0700000001', 'email' => 'head@example.test', 'effective_from' => '2026-01-01', 'is_current' => true]);
        $period = ReportingPeriod::create(['title' => 'Annual collection', 'type' => 'annual_census', 'year' => 2026, 'status' => 'open', 'opening_date' => now()->subDay(), 'closing_date' => now()->addDay(), 'created_by' => $admin->id]);
        $period->schools()->attach($s);
        $form = array_fill_keys(array_keys(StatisticalForm::sections()), []);
        $form['identity'] = ['date' => '2026-11-07', 'school_name' => 'Report School', 'registration_number' => 'REPORT-001', 'emis_code' => '00123', 'level' => 'Primary', 'funding' => 'Private', 'gender' => 'Mixed', 'boarding' => 'Day', 'headteacher_name' => 'Report Teacher'];
        $form['enrolment'] = ['classes' => ['year_1' => ['boys' => 0, 'girls' => 10]]];
        $form['religion'] = ['community' => ['learners' => ['catholics' => 10, 'anglicans' => 0, 'muslims' => 0, 'others' => 0]]];
        $form['academic'] = ['ple' => ['division_1' => ['candidates' => 10]]];
        foreach (StatisticalForm::sections()['facilities']['tables']['items']['rows'] as $key => $label) {
            $form['facilities']['items'][$key] = ['quantity' => 0, 'remarks' => 'Recorded zero for '.$label];
        }
        $form['utilities'] = ['water_sources' => ['Borehole'], 'water_reliable' => 'No', 'water_explanation' => 'Seasonal interruptions', 'power_sources' => []];
        $form['teaching'] = ['members' => [['name' => 'Report Teacher', 'gender' => 'Male', 'payroll' => 'Government', 'age' => 40, 'qualification' => 'Diploma', 'license_number' => '0012', 'religion' => 'Catholic', 'responsibility' => 'Class teacher']]];
        $form['support'] = ['members' => []];
        $form['studies'] = ['programmes' => [['name' => 'Report Teacher', 'course' => 'Education', 'institution' => '=DO_NOT_EXECUTE']]];
        $r = (new SchoolReturn)->forceFill(['school_id' => $s->id, 'reporting_period_id' => $period->id, 'status' => 'locked', 'submitted_at' => now(), 'submitted_by' => $head->id, 'profile_confirmed_at' => now(), 'profile_snapshot' => ['level' => 'Primary', 'registration_number' => 'REPORT-001'], 'statistical_form' => $form]);
        $r->save();
        $a = Announcement::create(['title' => 'Report notice', 'body' => 'Read this notice', 'created_by' => $admin->id, 'target_scope' => 'registered_schools', 'target_filters' => [], 'requires_acknowledgement' => true, 'published_at' => now(), 'publication_key' => 'report-notice']);
        NotificationRecord::create(['announcement_id' => $a->id, 'user_id' => $head->id, 'school_id' => $s->id, 'type' => 'announcement', 'subject' => 'Report notice', 'message' => 'Read this notice', 'status' => 'read', 'read_at' => now()]);
        $t = HelpdeskTicket::create(['school_id' => $s->id, 'opened_by' => $head->id, 'category' => 'Form entry', 'description' => 'Please help with this form', 'status' => 'resolved', 'priority' => 'normal']);
        $t->messages()->create(['author_id' => $admin->id, 'message' => 'Please retry.']);
        AuditEvent::create(['user_id' => $head->id, 'action' => 'submission.submit', 'auditable_type' => SchoolReturn::class, 'auditable_id' => $r->id]);

        return [$admin, $head, $s, $period, $r];
    }

    public function test_every_report_previews_and_exports_real_word_and_excel_files(): void
    {
        [$admin,$head,$school] = $this->context();
        Storage::fake('local');
        $this->actingAs($admin)->get(route('reports.index'))->assertOk();
        foreach (ReportCatalog::TYPES as $key => $label) {
            $filters = ['year' => 2026, 'school_id' => $school->id, 'basis' => 'submitted'];
            $this->actingAs($admin)->post(route('reports.preview'), ['report_type' => $key, ...$filters])->assertOk()->assertSee($label);
            foreach (['docx', 'xlsx'] as $format) {
                $export = app(ReportExportService::class)->generate($admin, $key, $format, $filters);
                $bytes = Storage::disk('local')->get($export->path);
                $this->assertStringStartsWith('PK', $bytes, $key);
                $tmp = tempnam(sys_get_temp_dir(), 'mdes-test-');
                file_put_contents($tmp, $bytes);
                try {
                    $zip = new \ZipArchive;
                    $this->assertTrue($zip->open($tmp));
                    $xml = $zip->getFromName($format === 'docx' ? 'word/document.xml' : 'xl/workbook.xml');
                    $this->assertNotFalse($xml);
                    $doc = new \DOMDocument;
                    $this->assertTrue($doc->loadXML($xml));
                    $zip->close();
                } finally {
                    unlink($tmp);
                }
            }
        }
        $this->actingAs($admin)->get(route('reports.index'))->assertOk();
    }

    public function test_excel_preserves_numeric_zero_identifiers_and_untrusted_text(): void
    {
        [$admin,$head,$school] = $this->context();
        Storage::fake('local');
        foreach (['school registry', 'enrolment', 'staff study programmes'] as $type) {
            $export = app(ReportExportService::class)->generate($admin, $type, 'xlsx', ['school_id' => $school->id]);
            $book = IOFactory::load(Storage::disk('local')->path($export->path));
            $values = [];
            $types = [];
            foreach ($book->getAllSheets() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    foreach ($row->getCellIterator() as $cell) {
                        $values[] = $cell->getValue();
                        $types[(string) $cell->getValue()] = $cell->getDataType();
                    }
                }
            }
            if ($type === 'school registry') {
                $this->assertContains('00123', $values);
            }
            if ($type === 'enrolment') {
                $this->assertContains(0, $values);
            }
            if ($type === 'staff study programmes') {
                $this->assertSame('s', $types['=DO_NOT_EXECUTE']);
            }
            $book->disconnectWorksheets();
        }
    }

    public function test_filters_basis_and_coverage_use_assigned_schools_without_dropping_zero(): void
    {
        [$admin,$head,$school,$period,$return] = $this->context();
        $other = $school->replicate();
        $other->ministry_registration_number = 'REPORT-OTHER';
        $other->emis_number = '00234';
        $other->diocesan_code = 'R-OTHER';
        $other->save();
        $period->schools()->attach($other);
        $report = app(ReportDataService::class)->build($admin, 'submission progress', ['year' => 2026]);
        $this->assertCount(2, $report['tables'][array_key_last($report['tables'])]['rows']);
        $this->assertSame('2', $report['metadata']['Assigned schools for this collection']);
        $report = app(ReportDataService::class)->build($admin, 'enrolment', ['year' => 2026, 'school_id' => $school->id, 'basis' => 'approved']);
        $this->assertEmpty($report['tables'][0]['rows']);
        $return->update(['status' => 'approved']);
        $report = app(ReportDataService::class)->build($admin, 'enrolment', ['year' => 2026, 'school_id' => $school->id, 'basis' => 'approved']);
        $this->assertCount(1, $report['tables'][0]['rows']);
        $this->assertSame(0, $report['tables'][0]['rows'][0][7]);
        $report = app(ReportDataService::class)->build($admin, 'enrolment', ['year' => 2025]);
        $this->assertEmpty($report['tables'][0]['rows']);
    }

    public function test_school_copies_are_private_and_versions_preserve_submitted_answers(): void
    {
        [$admin,$head,$school,$period,$return] = $this->context();
        $return->update(['status' => 'draft', 'submitted_at' => null]);
        $action = app(TransitionSchoolReturn::class);
        $action->submit($return, $head);
        $v1 = SubmissionVersion::where('school_return_id', $return->id)->firstOrFail();
        $hash = $v1->content_hash;
        $action->returnForCorrection($return->fresh(), $admin, 'Please correct the enrolment.');
        $form = $return->fresh()->statistical_form;
        $form['identity']['school_name'] = 'Second version school';
        $form['enrolment']['classes']['year_1']['boys'] = 20;
        $return->update(['statistical_form' => $form]);
        $action->submit($return->fresh(), $head);
        $action->approve($return->fresh(), $admin);
        $this->assertSame(2, SubmissionVersion::where('school_return_id', $return->id)->count());
        $this->assertSame($hash, $v1->fresh()->content_hash);
        $this->assertSame(0, $v1->fresh()->payload['form']['enrolment']['classes']['year_1']['boys']);
        $response = $this->actingAs($head)->get(route('returns.copy', [$return, 'format' => 'docx', 'version' => $v1->id]))->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'mdes-v1-');
        file_put_contents($tmp, $response->getContent());
        try {
            $zip = new \ZipArchive;
            $zip->open($tmp);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            $this->assertStringContainsString('Report School', $xml);
            $this->assertStringNotContainsString('Second version school', $xml);
        } finally {
            unlink($tmp);
        }
        $this->get(route('returns.copy', [$return, 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('returns.copy', [$return, 'format'=>'pdf', 'print'=>1]))->assertOk()->assertHeader('content-type','application/pdf')->assertHeader('content-disposition','inline; filename="MDES-'.$return->id.'-v2.pdf"');
        $foreignSchool = $school->replicate();
        $foreignSchool->diocesan_code = 'COPY-OTHER';
        $foreignSchool->ministry_registration_number = 'COPY-OTHER';
        $foreignSchool->emis_number = 'COPY-OTHER';
        $foreignSchool->save();
        $other = User::factory()->create(['school_id' => $foreignSchool->id]);
        $other->roles()->attach(Role::where('slug', Role::HEADTEACHER)->first());
        $this->actingAs($other)->get(route('returns.copy', $return))->assertForbidden();
        $foreign = (new SchoolReturn)->forceFill(['school_id' => $foreignSchool->id, 'reporting_period_id' => $period->id, 'status' => 'locked', 'submitted_at' => now(), 'statistical_form' => []]);
        $foreign->save();
        $foreignVersion = app(SubmissionVersionService::class)->capture($foreign);
        $this->actingAs($head)->get(route('returns.copy', [$return, 'version' => $foreignVersion->id]))->assertNotFound();
        $this->actingAs($head)->get(route('reports.index'))->assertForbidden();
        $this->post(route('reports.export'), ['report_type' => 'enrolment', 'format' => 'docx'])->assertForbidden();
    }

    public function test_draft_copy_includes_unsaved_invalid_entries_without_claiming_submission(): void
    {
        [$admin,$head,$school,$period,$return] = $this->context();
        $return->forceFill(['status' => 'draft', 'submitted_at' => null, 'statistical_form_drafts' => ['teaching' => ['revision' => 1, 'valid' => false, 'data' => ['members' => [['name' => 'Unfinished teacher', 'age' => '1']]]]]])->save();
        $response = $this->actingAs($head)->get(route('returns.copy', [$return, 'format' => 'xlsx']))->assertOk();
        $this->assertStringStartsWith('PK', $response->getContent());
        $this->assertSame('draft', $return->fresh()->status);
        $this->assertSame(0, SubmissionVersion::count());
        $this->get(route('returns.copy', [$return, 'format' => 'exe']))->assertSessionHasErrors('format');
    }

    public function test_renderers_do_not_silently_truncate_large_reports(): void
    {
        $this->expectException(ValidationException::class);
        app(ReportRenderer::class)->render(['title' => 'Large report', 'metadata' => [], 'tables' => [['name' => 'Rows', 'headers' => ['Value'], 'rows' => array_fill(0, 5001, [1])]]], 'pdf');
    }

    public function test_submitted_version_answers_cannot_be_overwritten(): void
    {
        [$admin,$head,$school,$period,$return] = $this->context();
        $version = app(SubmissionVersionService::class)->capture($return);
        $this->expectException(\LogicException::class);
        $version->update(['payload' => ['form' => ['identity' => ['school_name' => 'Rewritten']]]]);
    }
}
