<?php

namespace Tests\Unit;

use App\Models\Diocese;
use App\Models\District;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Services\AcademicPerformanceSectionService;
use App\Services\AttendanceWelfareSectionService;
use App\Services\EnrolmentSectionService;
use App\Services\InfrastructureSectionService;
use App\Services\StaffStatisticsSectionService;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\PhaseNineToFourteenSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticalSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolment_total_is_calculated_from_boys_and_girls(): void
    {
        $return = $this->schoolReturn();

        app(EnrolmentSectionService::class)->save($return, [
            ['class_level' => 'p1', 'boys' => 13, 'girls' => 17, 'day_scholars' => 30, 'boarders' => 0],
        ]);

        $this->assertSame(30, $return->enrolmentStatistics()->first()->total);
    }

    public function test_enrolment_rejects_invalid_day_boarding_total(): void
    {
        $this->expectException(DomainException::class);

        app(EnrolmentSectionService::class)->save($this->schoolReturn(), [
            ['class_level' => 'p1', 'boys' => 5, 'girls' => 5, 'day_scholars' => 9, 'boarders' => 2],
        ]);
    }

    public function test_attendance_rate_is_calculated(): void
    {
        $return = $this->schoolReturn();

        app(AttendanceWelfareSectionService::class)->save($return, [
            'enrolled_learners' => 100,
            'expected_attendance' => 500,
            'aggregate_attendance' => 450,
            'retained_learners' => 98,
            'dropouts' => 1,
            're_entries' => 1,
            'transitioned_learners' => 20,
            'welfare_cases_aggregated' => 0,
        ]);

        $this->assertEquals(90.00, (float) $return->attendanceWelfareStatistic()->first()->attendance_rate);
    }

    public function test_staff_statistics_calculate_vacancy_rate_and_ratio(): void
    {
        $return = $this->schoolReturn();
        app(EnrolmentSectionService::class)->save($return, [
            ['class_level' => 'p1', 'boys' => 50, 'girls' => 50, 'day_scholars' => 100, 'boarders' => 0],
        ]);

        app(StaffStatisticsSectionService::class)->save($return, [
            'male_teaching_staff' => 2,
            'female_teaching_staff' => 3,
            'male_non_teaching_staff' => 1,
            'female_non_teaching_staff' => 1,
            'qualified_teachers' => 4,
            'unqualified_teachers' => 1,
            'government_payroll_staff' => 4,
            'private_payroll_staff' => 3,
            'approved_establishment' => 8,
            'filled_positions' => 7,
        ]);

        $staff = $return->staffStatistic()->first();
        $this->assertSame(1, $staff->vacancies);
        $this->assertEquals(12.50, (float) $staff->vacancy_rate);
        $this->assertEquals(20.00, (float) $staff->pupil_teacher_ratio);
    }

    public function test_staff_statistics_reject_invalid_qualification_reconciliation(): void
    {
        $this->expectException(DomainException::class);

        app(StaffStatisticsSectionService::class)->save($this->schoolReturn(), [
            'male_teaching_staff' => 1,
            'female_teaching_staff' => 1,
            'male_non_teaching_staff' => 0,
            'female_non_teaching_staff' => 0,
            'qualified_teachers' => 1,
            'unqualified_teachers' => 0,
            'government_payroll_staff' => 1,
            'private_payroll_staff' => 0,
            'approved_establishment' => 2,
            'filled_positions' => 1,
        ]);
    }

    public function test_academic_performance_calculates_pass_rate(): void
    {
        $return = $this->schoolReturn();
        $this->seed(PhaseNineToFourteenSeeder::class);
        $exam = \App\Models\ExaminationType::query()->where('slug', 'ple')->firstOrFail();

        app(AcademicPerformanceSectionService::class)->save($return, [[
            'examination_type_id' => $exam->id,
            'registered_candidates' => 10,
            'candidates_sat' => 9,
            'absentees' => 1,
            'male_candidates' => 4,
            'female_candidates' => 6,
            'grade_distribution' => ['division 1' => 3, 'division 2' => 4, 'ungraded' => 2],
        ]]);

        $this->assertEquals(77.78, (float) $return->academicPerformanceStatistics()->first()->pass_rate);
    }

    public function test_academic_performance_rejects_results_above_sat_candidates(): void
    {
        $this->expectException(DomainException::class);
        $this->seed(PhaseNineToFourteenSeeder::class);
        $exam = \App\Models\ExaminationType::query()->firstOrFail();

        app(AcademicPerformanceSectionService::class)->save($this->schoolReturn(), [[
            'examination_type_id' => $exam->id,
            'registered_candidates' => 5,
            'candidates_sat' => 5,
            'absentees' => 0,
            'male_candidates' => 2,
            'female_candidates' => 3,
            'grade_distribution' => ['division 1' => 6],
        ]]);
    }

    public function test_infrastructure_gap_is_calculated(): void
    {
        $return = $this->schoolReturn();

        app(InfrastructureSectionService::class)->save($return, [[
            'facility_type' => 'classrooms',
            'total' => 4,
            'functional' => 3,
            'damaged' => 1,
            'required' => 6,
            'condition' => 'poor',
            'priority' => 'high',
        ]]);

        $this->assertSame(3, $return->infrastructureStatistics()->first()->gap);
    }

    private function schoolReturn(): SchoolReturn
    {
        $this->seed(DiocesanStructureSeeder::class);

        $school = School::query()->create([
            'diocese_id' => Diocese::query()->firstOrFail()->id,
            'parish_id' => Parish::query()->firstOrFail()->id,
            'district_id' => District::query()->firstOrFail()->id,
            'official_name' => 'stats school',
            'diocesan_code' => 'STAT-'.fake()->unique()->numberBetween(100, 999),
            'school_level' => 'primary',
            'ownership' => 'diocesan',
            'operational_status' => School::STATUS_ACTIVE,
        ]);

        $period = ReportingPeriod::query()->create([
            'title' => 'stats period',
            'type' => 'annual_census',
            'year' => 2026,
            'opening_date' => '2026-01-01',
            'closing_date' => '2026-02-01',
            'status' => ReportingPeriod::STATUS_OPEN,
        ]);

        return SchoolReturn::query()->create([
            'reporting_period_id' => $period->id,
            'school_id' => $school->id,
            'status' => SchoolReturn::STATUS_DRAFT,
        ]);
    }
}
