<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Diocese;
use App\Models\District;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAuditTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $school = School::create(['diocese_id' => Diocese::first()->id, 'parish_id' => Parish::first()->id, 'district_id' => District::first()->id, 'official_name' => 'Scoped headteacher school', 'ministry_registration_number' => 'AUDIT-MOE-1', 'diocesan_code' => 'PANEL-AUDIT', 'school_level' => 'primary', 'ownership' => 'other', 'operational_status' => 'active']);
        $head = User::factory()->create(['school_id' => $school->id]);
        $head->roles()->attach(Role::where('slug', Role::HEADTEACHER)->firstOrFail());
        $period = ReportingPeriod::create(['title' => 'Audit window', 'type' => 'annual_census', 'year' => 2026, 'status' => 'open', 'opening_date' => now()->subDay(), 'closing_date' => now()->addDay(), 'created_by' => $admin->id]);
        $period->schools()->attach($school);
        $return = SchoolReturn::create(['school_id' => $school->id, 'reporting_period_id' => $period->id, 'status' => 'draft']);

        return [$admin, $head, $school, $period, $return];
    }

    public function test_main_admin_pages_render_with_private_security_headers_and_accessible_shell(): void
    {
        [$admin] = $this->context();
        foreach (['dashboard', 'parishes.index', 'parishes.create', 'schools.index', 'schools.create', 'returns.index', 'administration.users.index', 'administration.users.create', 'announcements.index', 'announcements.create', 'alerts.index', 'reporting-periods.index', 'reporting-periods.create', 'profile.show', 'profile.password.edit'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertSee('Skip to main content')->assertSee('id="main-content"', false)->assertSee('admin-interface');
        }
        $response = $this->get(route('dashboard'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertSee('href="'.route('alerts.index').'" aria-label="Inbox:', false);
    }

    public function test_admin_cannot_start_or_edit_even_draft_school_forms(): void
    {
        [$admin,$head,$school,$period,$return] = $this->context();
        $this->actingAs($admin)->post(route('returns.start', [$period, $school]))->assertForbidden();
        $this->post(route('returns.form.save', [$return, 'identity']), ['data' => ['school_name' => 'Forged']])->assertForbidden();
        $this->get(route('returns.form.show', [$return, 'identity']))->assertOk()->assertViewHas('editable', false);
        $this->assertNull($return->fresh()->statistical_form);
    }

    public function test_headteacher_cannot_open_admin_interfaces_and_period_counts_are_scoped(): void
    {
        [$admin,$head,$school,$period,$return] = $this->context();
        $other = $school->replicate();
        $other->diocesan_code = 'OTHER-PANEL';
        $other->ministry_registration_number = 'AUDIT-MOE-2';
        $other->official_name = 'Other private school';
        $other->save();
        $period->schools()->attach($other);
        SchoolReturn::create(['school_id' => $other->id, 'reporting_period_id' => $period->id, 'status' => 'draft']);
        $this->actingAs($head)->get(route('schools.index'))->assertForbidden();
        foreach (['administration.users.index', 'administration.users.create', 'parishes.index', 'announcements.create', 'reporting-periods.create'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('reporting-periods.index'))->assertForbidden();
    }

    public function test_users_search_filters_and_malformed_list_filters_are_validated(): void
    {
        [$admin,$head,$school] = $this->context();
        $this->actingAs($admin)->get(route('administration.users.index', ['search' => 'AUDIT-MOE-1', 'account_type' => 'headteacher', 'status' => 'active']))->assertOk()->assertViewHas('users', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $head->id);
        $this->get(route('administration.users.index', ['search' => ['bad']]))->assertSessionHasErrors('search');
        $this->get(route('schools.index', ['search' => ['bad']]))->assertSessionHasErrors('search');
        $this->get(route('reporting-periods.index', ['year' => 'invalid']))->assertSessionHasErrors('year');
    }

    public function test_admins_share_announcement_history_while_headteachers_keep_private_inbox(): void
    {
        [$admin,$head] = $this->context();
        $otherAdmin = User::factory()->create();
        $otherAdmin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        Announcement::create(['created_by' => $otherAdmin->id, 'title' => 'Shared admin history', 'body' => 'A message', 'target_scope' => 'registered_schools', 'published_at' => now(), 'target_filters' => ['school_ids' => []]]);
        $this->actingAs($admin)->get(route('announcements.index'))->assertOk()->assertSee('Shared admin history')->assertSee($otherAdmin->name);
        $this->actingAs($head)->get(route('announcements.index'))->assertOk()->assertDontSee('Shared admin history');
    }
}
