<?php

namespace Tests\Feature;

use App\Actions\StartSchoolReturn;
use App\Actions\TransitionSchoolReturn;
use App\Models\Diocese;
use App\Models\District;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Services\AcademicPerformanceSectionService;
use App\Services\AttendanceWelfareSectionService;
use App\Services\CatholicIdentitySectionService;
use App\Services\EnrolmentSectionService;
use App\Services\GovernanceComplianceSectionService;
use App\Services\InfrastructureSectionService;
use App\Services\StaffStatisticsSectionService;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Database\Seeders\PhaseNineToFourteenSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_window_list_filters_and_detail_render(): void
    {
        [$admin,$school,$period]=$this->adminSchoolAndPeriod();
        $this->actingAs($admin)->get(route('reporting-periods.index'))->assertOk()->assertSee('New window')->assertSee($period->title);
        $this->get(route('reporting-periods.index',['status'=>'closed']))->assertOk()->assertViewHas('periods',fn($rows)=>$rows->total()===0);
        $this->get(route('reporting-periods.show',$period))->assertOk()->assertSee('Submission Windows')->assertSee($school->official_name);
    }

    public function test_authorized_user_can_create_reporting_period_and_start_return(): void
    {
        [$admin, $school] = $this->adminAndSchool();

        $this->actingAs($admin)
            ->post('/school-statistics/reporting-periods', [
                'title' => '2026 annual census',
                'type' => 'annual_census',
                'year' => 2026,
                'opening_date' => '2026-01-01',
                'closing_date' => '2026-02-01',
                'status' => ReportingPeriod::STATUS_OPEN,
                'school_ids' => [$school->id],
            ])
            ->assertRedirect();

        $period = ReportingPeriod::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('returns.start', [$period, $school]))
            ->assertRedirect();

        $this->assertDatabaseHas('school_returns', [
            'reporting_period_id' => $period->id,
            'school_id' => $school->id,
            'status' => SchoolReturn::STATUS_DRAFT,
        ]);
        $this->assertSame(7, SchoolReturn::query()->first()->sections()->count());
    }

    public function test_return_cannot_be_submitted_until_required_sections_are_complete(): void
    {
        [$admin, $school, $period] = $this->adminSchoolAndPeriod();
        $return = app(StartSchoolReturn::class)->handle($period, $school, $admin);

        $this->expectException(DomainException::class);
        app(TransitionSchoolReturn::class)->submit($return, $admin);
    }

    public function test_complete_return_can_move_through_submit_certify_approve_and_lock(): void
    {
        [$admin, $school, $period] = $this->adminSchoolAndPeriod();
        $return = app(StartSchoolReturn::class)->handle($period, $school, $admin);
        $this->completeSections($return);

        $transition = app(TransitionSchoolReturn::class);
        $transition->submit($return->refresh(), $admin);
        $transition->certify($return->refresh(), $admin);
        $transition->approve($return->refresh(), $admin);
        $transition->lock($return->refresh(), $admin);

        $this->assertSame(SchoolReturn::STATUS_LOCKED, $return->refresh()->status);
    }

    public function test_locked_return_cannot_be_edited(): void
    {
        [$admin, $school, $period] = $this->adminSchoolAndPeriod();
        $return = app(StartSchoolReturn::class)->handle($period, $school, $admin);
        $this->completeSections($return);
        $transition = app(TransitionSchoolReturn::class);
        $transition->submit($return->refresh(), $admin);
        $transition->certify($return->refresh(), $admin);
        $transition->approve($return->refresh(), $admin);
        $transition->lock($return->refresh(), $admin);

        $this->expectException(DomainException::class);
        app(EnrolmentSectionService::class)->save($return->refresh(), [
            ['class_level' => 'p1', 'boys' => 1, 'girls' => 1],
        ]);
    }

    private function completeSections(SchoolReturn $return): void
    {
        app(EnrolmentSectionService::class)->save($return, [
            ['class_level' => 'p1', 'boys' => 10, 'girls' => 12, 'day_scholars' => 22, 'boarders' => 0],
            ['class_level' => 'p2', 'boys' => 9, 'girls' => 11, 'day_scholars' => 20, 'boarders' => 0],
        ]);

        app(AttendanceWelfareSectionService::class)->save($return, [
            'enrolled_learners' => 42,
            'expected_attendance' => 420,
            'aggregate_attendance' => 378,
            'retained_learners' => 40,
            'dropouts' => 1,
            're_entries' => 1,
            'transitioned_learners' => 10,
            'welfare_cases_aggregated' => 0,
        ]);

        app(StaffStatisticsSectionService::class)->save($return, [
            'male_teaching_staff' => 2,
            'female_teaching_staff' => 4,
            'male_non_teaching_staff' => 1,
            'female_non_teaching_staff' => 2,
            'qualified_teachers' => 5,
            'unqualified_teachers' => 1,
            'government_payroll_staff' => 4,
            'private_payroll_staff' => 5,
            'approved_establishment' => 10,
            'filled_positions' => 9,
        ]);

        $exam = \App\Models\ExaminationType::query()->firstOrFail();
        app(AcademicPerformanceSectionService::class)->save($return, [[
            'examination_type_id' => $exam->id,
            'registered_candidates' => 10,
            'candidates_sat' => 9,
            'absentees' => 1,
            'male_candidates' => 4,
            'female_candidates' => 6,
            'grade_distribution' => ['division 1' => 3, 'division 2' => 4, 'ungraded' => 2],
        ]]);

        app(InfrastructureSectionService::class)->save($return, [[
            'facility_type' => 'classrooms',
            'total' => 4,
            'functional' => 4,
            'damaged' => 0,
            'required' => 5,
            'condition' => 'fair',
            'priority' => 'normal',
        ]]);

        app(CatholicIdentitySectionService::class)->save($return, [
            'retreats_held' => 1,
            'parish_school_engagements' => 2,
            'has_chaplaincy' => true,
            'catechesis_available' => true,
            'values_education_available' => true,
            'safeguarding_structure_available' => true,
            'counselling_structure_available' => true,
        ]);

        app(GovernanceComplianceSectionService::class)->save($return, [
            'board_active' => true,
            'board_meetings_held' => 3,
            'pta_active' => true,
            'child_protection_policy_available' => true,
            'financial_policy_available' => true,
            'risk_register_available' => true,
            'mandatory_reports_submitted' => true,
            'open_compliance_issues' => 0,
        ]);
    }

    private function adminAndSchool(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class, PhaseNineToFourteenSeeder::class]);
        $role = Role::query()->where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail();
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        $school = School::query()->create([
            'diocese_id' => Diocese::query()->firstOrFail()->id,
            'parish_id' => Parish::query()->firstOrFail()->id,
            'district_id' => District::query()->firstOrFail()->id,
            'official_name' => 'return school',
            'diocesan_code' => 'RET-001',
            'school_level' => 'primary',
            'ownership' => 'diocesan',
            'operational_status' => School::STATUS_ACTIVE,
        ]);

        return [$admin, $school];
    }

    private function adminSchoolAndPeriod(): array
    {
        [$admin, $school] = $this->adminAndSchool();
        $period = ReportingPeriod::query()->create([
            'title' => '2026 annual census',
            'type' => 'annual_census',
            'year' => 2026,
            'opening_date' => '2026-01-01',
            'closing_date' => '2026-02-01',
            'status' => ReportingPeriod::STATUS_OPEN,
            'created_by' => $admin->id,
        ]);
        $period->schools()->attach($school);

        return [$admin, $school, $period];
    }
}
