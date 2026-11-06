<?php

namespace Tests\Feature;

use App\Models\Diocese;
use App\Models\District;
use App\Models\JurisdictionAssignment;
use App\Models\Parish;
use App\Models\Permission;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiocesanDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $period = ReportingPeriod::create(['title' => '2026 census', 'type' => 'annual_census', 'year' => 2026, 'status' => 'open', 'opening_date' => '2026-01-01', 'closing_date' => '2026-01-31', 'created_by' => $user->id]);

        return [$user, $period];
    }

    private function school(ReportingPeriod $period, string $code, string $status = 'approved', int $learners = 20): SchoolReturn
    {
        $school = School::create(['diocese_id' => Diocese::first()->id, 'parish_id' => Parish::first()->id, 'district_id' => District::first()->id, 'official_name' => $code, 'diocesan_code' => $code, 'school_level' => 'primary', 'ownership' => 'diocesan', 'operational_status' => 'active']);
        $period->schools()->attach($school);

        return SchoolReturn::create(['school_id' => $school->id, 'reporting_period_id' => $period->id, 'status' => $status,
            'statistical_form' => ['enrolment' => ['classes' => [['boys' => $learners, 'girls' => 0]]], 'teaching' => ['members' => [['name' => 'Teacher']]], 'utilities' => ['water_reliable' => 'No'], 'facilities' => ['items' => ['desk_capacity' => ['quantity' => 0], 'boys_toilets' => ['quantity' => 0]]]]]);
    }

    public function test_submitted_data_excludes_drafts(): void
    {
        [$user, $period] = $this->context();
        $this->school($period, 'Approved school');
        $this->school($period, 'Draft school', 'draft', 90);
        $response = $this->actingAs($user)->get(route('dashboard', ['year' => 2026]));
        $response->assertOk()->assertViewHas('boys', 20)->assertViewHas('submittedCount', 1)->assertDontSee('Overdue: Not submitted')->assertDontSee('id="schools-needing-attention"', false);
        $this->assertSame(20, $response->viewData('summary')['shortfall']['value']);
        $this->get(route('dashboard', ['year' => 2026, 'basis' => 'provisional']))->assertOk()->assertViewHas('boys', 20);
        $this->get('/schools-needing-attention')->assertNotFound();

    }

    public function test_recent_submissions_use_submission_dates_and_exclude_drafts(): void
    {
        [$user, $period] = $this->context();
        $older = $this->school($period, 'Earlier submission');
        $older->update(['submitted_at' => '2026-02-01 10:00:00', 'updated_at' => '2026-11-06 10:00:00']);
        $recent = $this->school($period, 'Recent submission', 'submitted');
        $recent->update(['submitted_at' => '2026-03-01 10:00:00']);
        $this->school($period, 'Unsubmitted draft', 'draft');
        $response = $this->actingAs($user)->get(route('dashboard', ['year' => 2026]));
        $response->assertOk()->assertSee('Recent school submissions')->assertDontSee('Schools with unreliable water by parish')->assertDontSee('Desk seating shortfall by parish')->assertDontSee('Snapshot')->assertDontSee('Assigned → Started');
        $this->assertSame([$recent->id, $older->id], $response->viewData('recentSubmissions')->pluck('id')->all());
    }

    public function test_reporting_status_filter_includes_unstarted_schools_and_preserves_drilldowns(): void
    {
        [$user, $period] = $this->context();
        $submitted = $this->school($period, 'Submitted school', 'locked', 40);
        $this->school($period, 'Draft school', 'draft', 100);
        $unstarted = $this->school($period, 'Unstarted school');
        $unstarted->delete();
        $response = $this->actingAs($user)->get(route('dashboard', ['year' => 2026, 'reporting_status' => 'outstanding']));
        $response->assertOk()->assertViewHas('submittedCount', 0)->assertViewHas('outstandingCount', 2)->assertViewHas('boys', null);
        $this->assertCount(2, $response->viewData('expected'));
        $response = $this->get(route('dashboard', ['year' => 2026, 'reporting_status' => 'submitted']));
        $response->assertOk()->assertViewHas('boys', 40)->assertViewHas('outstandingCount', 0);
        $this->get(route('dashboard.details', ['year' => 2026, 'metric' => 'schools', 'reporting_status' => 'outstanding']))
            ->assertOk()->assertViewHas('detailCount', 2)->assertDontSee('Submitted school');
        $this->get(route('dashboard', ['reporting_status' => 'invalid']))->assertSessionHasErrors('reporting_status');
    }

    public function test_largest_schools_are_ranked_within_each_level_using_submitted_data(): void
    {
        [$user, $period] = $this->context();
        foreach (['Small' => 10, 'Largest' => 400, 'Third' => 100, 'Second' => 250] as $name => $count) {
            $this->school($period, $name, 'locked', $count);
        }
        $secondary = $this->school($period, 'Secondary leader', 'locked', 600);
        $secondary->school->update(['school_level' => 'secondary']);
        $this->school($period, 'Draft excluded', 'draft', 9000);
        $missing = $this->school($period, 'Missing enrolment', 'locked', 8000);
        $missing->update(['statistical_form' => ['teaching' => ['members' => []]]]);
        $response = $this->actingAs($user)->get(route('dashboard', ['year' => 2026]));
        $response->assertOk()->assertSee('Largest schools by enrolment');
        $ranked = $response->viewData('largestSchools');
        $this->assertSame(['Largest', 'Second', 'Third'], $ranked['primary']->map(fn ($r) => $r['school']->official_name)->all());
        $this->assertSame([400, 250, 100], $ranked['primary']->pluck('learners')->all());
        $this->assertSame('Secondary leader', $ranked['secondary']->first()['school']->official_name);
        $filtered = $this->get(route('dashboard', ['year' => 2026, 'level' => 'secondary']));
        $filtered->assertOk();
        $this->assertSame(['secondary'], $filtered->viewData('largestSchools')->keys()->all());
    }

    public function test_dashboard_links_open_matching_school_and_metric_details(): void
    {
        [$user, $period] = $this->context();
        $approved = $this->school($period, 'Approved detail school');
        $draft = $this->school($period, 'Draft detail school', 'draft', 90);
        $notStarted = $this->school($period, 'Not started detail school');
        $notStarted->delete();
        $this->actingAs($user)->get(route('dashboard.details', ['year' => 2026, 'metric' => 'schools', 'stage' => 'outstanding']))
            ->assertOk()->assertViewHas('detailCount', 2)->assertSee('Draft detail school')->assertSee('Not started detail school')->assertDontSee('Approved detail school')
            ->assertSee(route('reporting-periods.show', $period));
        $official = $this->get(route('dashboard.details', ['year' => 2026, 'metric' => 'learners']));
        $official->assertOk()->assertViewHas('detailTotal', 20)->assertViewHas('detailCount', 1)->assertSee(route('returns.form.show', [$approved, 'enrolment']));
        $this->get(route('dashboard.details', ['year' => 2026, 'metric' => 'learners', 'basis' => 'provisional']))
            ->assertOk()->assertViewHas('detailTotal', 20)->assertViewHas('detailCount', 1);
        $this->get(route('dashboard.details', ['year' => 2026, 'metric' => 'learners', 'gender' => 'girls']))
            ->assertOk()->assertViewHas('detailTotal', 0)->assertViewHas('detailCount', 1);
        $this->get(route('dashboard.details', ['year' => 2026, 'metric' => 'schools', 'stage' => 'submitted']))
            ->assertOk()->assertViewHas('detailCount', 1)->assertSee('Approved detail school')->assertDontSee('Draft detail school');
        $this->get(route('dashboard.details', ['year' => 2026, 'metric' => 'learners', 'level' => 'secondary']))
            ->assertOk()->assertViewHas('detailCount', 0);
        $this->get(route('dashboard.details', ['year' => 2026, 'metric' => 'water']))
            ->assertOk()->assertViewHas('detailCount', 1)->assertSee(route('returns.form.show', [$approved, 'utilities']));
    }

    public function test_dashboard_excludes_previous_year_and_unassigned_returns(): void
    {
        [$user, $period] = $this->context();
        $r = $this->school($period, 'Matched school');
        $this->school($period, 'New school', 'approved', 500);
        $old = $period->replicate();
        $old->year = 2025;
        $old->save();
        $old->schools()->attach($r->school_id);
        SchoolReturn::create(['school_id' => $r->school_id, 'reporting_period_id' => $old->id, 'status' => 'locked', 'statistical_form' => ['enrolment' => ['classes' => [['boys' => 10, 'girls' => 0]]]]]);
        $orphan = $this->school($period, 'Unassigned school', 'approved', 9000);
        $period->schools()->detach($orphan->school_id);
        $response = $this->actingAs($user)->get(route('dashboard', ['year' => 2026]));
        $response->assertOk()->assertViewHas('boys', 520);
        $response->assertViewHas('submittedCount', 2)->assertDontSee('Comparable enrolment change');
        $this->assertCount(2, $response->viewData('expected'));
    }

    public function test_school_details_are_paginated_and_scoped_by_school_filters(): void
    {
        [$user, $period] = $this->context();
        for ($i = 0; $i < 22; $i++) {
            $this->school($period, 'School '.$i);
        }
        $response = $this->actingAs($user)->get(route('dashboard.details', ['year' => 2026, 'metric' => 'schools', 'stage' => 'submitted', 'page' => 2]));
        $response->assertOk();
        $this->assertSame(22, $response->viewData('detailRows')->total());
        $this->assertCount(2, $response->viewData('detailRows'));
        $empty = $this->get(route('dashboard', ['year' => 2026, 'level' => 'secondary']));
        $empty->assertOk()->assertViewHas('boys', null)->assertViewHas('submittedCount', 0);
        $empty->assertSee('Reporting scope not configured');
    }

    public function test_parish_user_cannot_see_other_parish_totals_or_school_details(): void
    {
        [$admin, $period] = $this->context();
        $visible = $this->school($period, 'Visible school');
        $hidden = $this->school($period, 'Hidden school', 'approved', 9000);
        $otherParish = Parish::where('id', '!=', $visible->school->parish_id)->firstOrFail();
        $hidden->school->update(['parish_id' => $otherParish->id]);
        $role = Role::create(['slug' => 'dashboard-parish-reader', 'name' => 'Dashboard parish reader']);
        $role->permissions()->sync(Permission::whereIn('slug', ['returns.view', 'schools.view.assigned'])->pluck('id'));
        $reader = User::factory()->create();
        $reader->roles()->attach($role);
        JurisdictionAssignment::create(['user_id' => $reader->id, 'parish_id' => $visible->school->parish_id, 'assignment_type' => 'parish_coordinator', 'effective_from' => '2020-01-01', 'is_active' => true]);
        $response = $this->actingAs($reader)->get(route('dashboard.details', ['year' => 2026, 'metric' => 'learners']));
        $response->assertOk()->assertViewHas('detailTotal', 20)->assertSee('Visible school')->assertDontSee('Hidden school');
        $this->assertSame(1, $response->viewData('detailRows')->total());
    }
}
