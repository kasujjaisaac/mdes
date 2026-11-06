<?php

namespace Tests\Feature;

use App\Actions\ConvertNeedToProject;
use App\Models\DevelopmentNeed;
use App\Models\Diocese;
use App\Models\District;
use App\Models\InspectionFinding;
use App\Models\Parish;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\FinancialStatisticsService;
use App\Services\InspectionService;
use App\Services\ProductionReadinessService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemainingPhasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_phase_routes_render_for_super_admin(): void
    {
        [$admin] = $this->adminAndSchool();

        foreach ([
            route('notifications.index'),
            route('reports.index'),
            route('analytics.index'),
            route('data-quality.index'),
            route('audit-trail.index'),
            route('production-readiness.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_inspection_service_blocks_unverified_high_risk_finding_closure(): void
    {
        [$admin, $school] = $this->adminAndSchool();
        $inspection = app(InspectionService::class)->schedule([
            'school_id' => $school->id,
            'inspector_id' => $admin->id,
            'type' => 'routine',
            'purpose' => 'term monitoring',
            'planned_date' => '2026-09-01',
        ]);
        $finding = InspectionFinding::query()->create([
            'inspection_id' => $inspection->id,
            'domain' => 'safety',
            'severity' => 'high',
            'finding' => 'unsafe classroom block',
        ]);

        $this->expectException(DomainException::class);
        app(InspectionService::class)->closeFinding($finding, false);
    }

    public function test_approved_need_converts_to_project_without_overwriting_estimated_cost(): void
    {
        [, $school] = $this->adminAndSchool();
        $need = DevelopmentNeed::query()->create([
            'school_id' => $school->id,
            'category' => 'classrooms',
            'description' => 'two classroom block',
            'urgency' => 'high',
            'estimated_cost' => 100000,
            'review_status' => 'approved',
        ]);

        $project = app(ConvertNeedToProject::class)->handle($need, [
            'title' => 'classroom block phase one',
            'approved_budget' => 75000,
        ]);

        $this->assertEquals(100000.00, (float) $need->refresh()->estimated_cost);
        $this->assertEquals(75000.00, (float) $project->approved_budget);
        $this->assertSame('funded', $need->funding_status);
    }

    public function test_financial_service_calculates_totals_and_variance(): void
    {
        [, $school] = $this->adminAndSchool();

        $stat = app(FinancialStatisticsService::class)->save($school, [
            'budget_year' => 2026,
            'budget_categories' => ['operations' => 1000, 'capital' => 500],
            'income_categories' => ['fees' => 1200],
            'expenditure_categories' => ['operations' => 900],
        ]);

        $this->assertEquals(1500.00, (float) $stat->approved_budget_total);
        $this->assertEquals(600.00, (float) $stat->variance);
    }

    public function test_production_readiness_snapshot_is_recorded(): void
    {
        [$admin] = $this->adminAndSchool();

        $review = app(ProductionReadinessService::class)->createSnapshot($admin);

        $this->assertSame('not production ready', $review->classification);
        $this->assertNotEmpty($review->outstanding_decisions);
        $this->assertContains('documents', $review->implemented_modules);
    }

    private function adminAndSchool(): array
    {
        $this->seed(DatabaseSeeder::class);
        $role = Role::query()->where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail();
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        $school = School::query()->create([
            'diocese_id' => Diocese::query()->firstOrFail()->id,
            'parish_id' => Parish::query()->firstOrFail()->id,
            'district_id' => District::query()->firstOrFail()->id,
            'official_name' => 'remaining phases school',
            'diocesan_code' => 'REM-'.fake()->unique()->numberBetween(100, 999),
            'school_level' => 'primary',
            'ownership' => 'diocesan',
            'operational_status' => School::STATUS_ACTIVE,
        ]);

        return [$admin, $school];
    }
}
