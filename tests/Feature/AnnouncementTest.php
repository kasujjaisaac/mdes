<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Diocese;
use App\Models\District;
use App\Models\NotificationRecord;
use App\Models\Parish;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Database\Seeders\SchoolRepresentativeRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function setupAudience(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class, SchoolRepresentativeRoleSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $schools = [];
        $reps = [];
        foreach (['primary', 'secondary', 'primary'] as $i => $level) {
            $school = School::create(['diocese_id' => Diocese::first()->id, 'parish_id' => Parish::first()->id, 'district_id' => District::first()->id, 'official_name' => 'Audience school '.$i, 'diocesan_code' => 'ANN-'.$i, 'school_level' => $level, 'funding_classification' => $i === 2 ? 'Private' : 'Government', 'ownership' => 'diocesan', 'operational_status' => 'active']);
            $rep = User::factory()->create(['school_id' => $school->id, 'status' => 'active']);
            $rep->roles()->attach(Role::where('slug', 'headteacher')->firstOrFail());
            $schools[] = $school;
            $reps[] = $rep;
        }

        return [$admin, $schools, $reps];
    }

    public function test_filtered_preview_and_publication_deliver_once_to_registered_profiles(): void
    {
        [$admin,$schools,$reps] = $this->setupAudience();
        $this->actingAs($admin)->get(route('announcements.create'))->assertOk()->assertSee('Choose recipient schools');
        $preview = $this->post(route('announcements.preview'), ['title' => 'Primary meeting', 'body' => 'Meeting details', 'levels' => ['primary'], 'funding' => ['Government']])->assertOk();
        $this->assertCount(1, $preview->viewData('audience')['schools']);
        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('school_returns', 0);
        $token = $preview->viewData('token');
        $this->post(route('announcements.publish'), ['preview_token' => $token])->assertSessionHasNoErrors()->assertRedirect(route('announcements.index'));
        $this->post(route('announcements.publish'), ['preview_token' => $token])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('announcements', 1);
        $this->assertDatabaseCount('notification_records', 1);
        $this->assertDatabaseHas('notification_records', ['user_id' => $reps[0]->id, 'type' => 'announcement', 'subject' => 'Primary meeting']);
        $this->actingAs($reps[0])->get(route('announcements.index'))->assertOk()->assertSee('Meeting details');
        $alert = NotificationRecord::first();
        $this->post(route('alerts.read', $alert))->assertSessionHasNoErrors();
        $this->assertNotNull($alert->fresh()->read_at);
        $this->actingAs($reps[1])->get(route('announcements.index'))->assertOk()->assertDontSee('Meeting details');
        $this->post(route('alerts.read', $alert))->assertForbidden();
        $this->actingAs($admin)->get(route('announcements.index'))->assertOk()->assertSee('Primary meeting');
    }

    public function test_no_filters_include_all_and_changed_audience_requires_new_preview(): void
    {
        [$admin,$schools,$reps] = $this->setupAudience();
        $preview = $this->actingAs($admin)->post(route('announcements.preview'), ['title' => 'All schools', 'body' => 'Update'])->assertOk();
        $this->assertCount(3, $preview->viewData('audience')['schools']);
        $reps[0]->update(['status' => 'suspended']);
        $this->post(route('announcements.publish'), ['preview_token' => $preview->viewData('token')])->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('announcements', 0);
        $preview = $this->post(route('announcements.preview'), ['title' => 'All schools', 'body' => 'Update'])->assertOk();
        $this->assertCount(1, $preview->viewData('audience')['unreachable']);
        $this->post(route('announcements.publish'), ['preview_token' => $preview->viewData('token')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notification_records', 2);
    }

    public function test_representatives_cannot_publish_and_tokens_are_verified(): void
    {
        [$admin,$schools,$reps] = $this->setupAudience();
        $this->actingAs($reps[0])->get(route('announcements.create'))->assertForbidden();
        $this->post(route('announcements.preview'), ['title' => 'No', 'body' => 'No'])->assertForbidden();
        $this->post(route('announcements.publish'))->assertForbidden();
        $this->actingAs($admin)->post(route('announcements.publish'), ['preview_token' => 'tampered'])->assertSessionHasErrors('preview_token');
        $preview = $this->post(route('announcements.preview'), ['title' => 'Expiry', 'body' => 'Test'])->assertOk();
        $this->travel(31)->minutes();
        $this->post(route('announcements.publish'), ['preview_token' => $preview->viewData('token')])->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_no_reachable_accounts_cannot_publish(): void
    {
        [$admin,$schools,$reps] = $this->setupAudience();
        foreach ($reps as $rep) {
            $rep->update(['status' => 'suspended']);
        }
        $preview = $this->actingAs($admin)->post(route('announcements.preview'), ['title' => 'No recipients', 'body' => 'Test'])->assertOk();
        $this->post(route('announcements.publish'), ['preview_token' => $preview->viewData('token')])->assertSessionHasErrors('audience');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_required_popup_persists_until_school_acknowledges_and_counts_school_once(): void
    {
        [$admin, $schools, $reps] = $this->setupAudience();
        $preview = $this->actingAs($admin)->post(route('announcements.preview'), ['title' => 'Required school update', 'body' => 'Please read carefully.']);
        $this->post(route('announcements.publish'), ['preview_token' => $preview->viewData('token')])->assertSessionHasNoErrors();
        $this->assertTrue(Announcement::first()->requires_acknowledgement);
        $alert = NotificationRecord::where('user_id', $reps[0]->id)->firstOrFail();
        $this->actingAs($reps[0])->get(route('announcements.index'))->assertOk()->assertSee('<dialog class="required-announcement"', false)->assertSee('I have read this announcement');
        $this->get(route('announcements.index'))->assertSee('<dialog class="required-announcement"', false);
        $this->assertNull($alert->fresh()->read_at);
        $this->post(route('alerts.read', $alert))->assertSessionHasNoErrors();
        $acknowledgedAt = $alert->fresh()->read_at;
        $this->get(route('announcements.index'))->assertDontSee('<dialog class="required-announcement"', false);
        $this->travel(5)->minutes();
        $this->post(route('alerts.read', $alert));
        $this->assertEquals($acknowledgedAt, $alert->fresh()->read_at);
        $this->actingAs($admin)->get(route('announcements.index'))->assertOk()->assertSee('1 / 3 schools')->assertSee('33% acknowledged')->assertSee('2 awaiting acknowledgement')->assertSee('Audience school 0')->assertSee('Not acknowledged');
        $this->post(route('alerts.read', $alert))->assertForbidden();
        $this->actingAs($reps[1])->get(route('announcements.index'))->assertSee('<dialog class="required-announcement"', false);
    }

    public function test_multiple_announcements_are_presented_in_order(): void
    {
        [$admin, $schools, $reps] = $this->setupAudience();
        foreach (['First notice', 'Second notice'] as $title) {
            $preview = $this->actingAs($admin)->post(route('announcements.preview'), ['title' => $title, 'body' => 'School update']);
            $this->post(route('announcements.publish'), ['preview_token' => $preview->viewData('token')])->assertSessionHasNoErrors();
        }
        $first = NotificationRecord::where('user_id', $reps[0]->id)->oldest('id')->firstOrFail();
        $this->actingAs($reps[0])->get(route('announcements.index'))->assertSee('id="required-announcement-title">First notice', false);
        $this->post(route('alerts.read', $first))->assertSessionHasNoErrors();
        $this->get(route('announcements.index'))->assertSee('id="required-announcement-title">Second notice', false);
    }

    public function test_single_school_selection_overrides_categories_and_delivers_only_to_that_school(): void
    {
        [$admin, $schools, $reps] = $this->setupAudience();
        $this->actingAs($admin)->get(route('announcements.create'))->assertOk()->assertSee('Select schools')->assertSee('name="school_ids[]"', false);
        $preview = $this->post(route('announcements.preview'), ['title' => 'Single school notice', 'body' => 'For this school only.', 'school_ids' => [$schools[0]->id], 'levels' => ['secondary'], 'funding' => ['Private']])->assertOk();
        $this->assertEquals([$schools[0]->id], $preview->viewData('audience')['schools']->pluck('id')->all());
        $this->post(route('announcements.publish'), ['preview_token' => $preview->viewData('token')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notification_records', 1);
        $this->assertDatabaseHas('notification_records', ['user_id' => $reps[0]->id, 'subject' => 'Single school notice']);
        $this->actingAs($reps[1])->get(route('announcements.index'))->assertDontSee('Single school notice');
    }

    public function test_single_school_must_be_valid_and_within_publishers_scope(): void
    {
        [$admin, $schools, $reps] = $this->setupAudience();
        $this->actingAs($admin)->post(route('announcements.preview'), ['title' => 'Test', 'body' => 'Test', 'school_ids' => [999999]])->assertSessionHasErrors('school_ids.0');
        $role = Role::create(['slug' => 'announcement-publisher', 'name' => 'Announcement publisher']);
        $role->permissions()->sync(Permission::where('slug', 'communications.manage')->pluck('id'));
        $publisher = User::factory()->create();
        $publisher->roles()->attach($role);
        $this->actingAs($publisher)->post(route('announcements.preview'), ['title' => 'Test', 'body' => 'Test', 'school_ids' => [$schools[0]->id]])->assertForbidden();
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_multiple_selected_schools_receive_announcement_without_including_other_schools(): void
    {
        [$admin, $schools, $reps] = $this->setupAudience();
        $preview = $this->actingAs($admin)->post(route('announcements.preview'), ['title' => 'Selected schools only', 'body' => 'An update for two schools.', 'school_ids' => [$schools[0]->id, $schools[2]->id]])->assertOk();
        $this->assertCount(2, $preview->viewData('audience')['schools']);
        $this->post(route('announcements.publish'), ['preview_token' => $preview->viewData('token')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notification_records', 2);
        $this->assertDatabaseHas('notification_records', ['user_id' => $reps[0]->id]);
        $this->assertDatabaseHas('notification_records', ['user_id' => $reps[2]->id]);
        $this->assertDatabaseMissing('notification_records', ['user_id' => $reps[1]->id]);
    }
}
