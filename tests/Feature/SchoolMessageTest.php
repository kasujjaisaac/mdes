<?php

namespace Tests\Feature;

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

class SchoolMessageTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class, SchoolRepresentativeRoleSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $school = School::create(['diocese_id' => Diocese::first()->id, 'parish_id' => Parish::first()->id, 'district_id' => District::first()->id, 'official_name' => 'Message School', 'diocesan_code' => 'MESSAGE-1', 'school_level' => 'primary', 'ownership' => 'diocesan', 'operational_status' => 'active']);
        $rep = $this->representative($school);

        return [$admin, $school, $rep];
    }

    private function representative(School $school, string $status = 'active'): User
    {
        $user = User::factory()->create(['school_id' => $school->id, 'status' => $status]);
        $user->roles()->attach(Role::where('slug', 'headteacher')->firstOrFail());

        return $user;
    }

    public function test_admin_can_message_registered_school_without_a_submission(): void
    {
        [$admin,$school,$rep] = $this->context();
        $this->actingAs($admin)->get(route('schools.index'))->assertOk()->assertSee(route('schools.message', $school));
        $this->get(route('alerts.index', ['school_id' => $school->id]))->assertOk()->assertViewHas('selectedSchoolId', $school->id)->assertDontSee('class="card alert-compose"', false);
        $this->post(route('alerts.store'), ['school_id' => $school->id, 'subject' => 'School meeting', 'message' => 'Please attend the school meeting.'])->assertSessionHasNoErrors()->assertRedirect(route('alerts.index'));
        $this->assertDatabaseCount('notification_records', 1);
        $this->assertDatabaseCount('school_returns', 0);
        $alert = NotificationRecord::where('user_id', $rep->id)->firstOrFail();
        $this->assertSame('school_message', $alert->type);
        $this->assertSame($admin->id, $alert->sender_id);
        $this->assertNull($alert->school_return_id);
        $this->assertNull($alert->read_at);
        $this->actingAs($rep)->get(route('alerts.index'))->assertOk()->assertSee('School meeting')->assertSee('Please attend the school meeting.')->assertViewHas('unreadCount', 1);
        $this->post(route('alerts.read', $alert))->assertSessionHasNoErrors();
        $this->assertNotNull($alert->fresh()->read_at);
    }

    public function test_message_page_fixes_the_recipient_to_the_school_in_the_url(): void
    {
        [$admin,$school,$rep] = $this->context();
        $other = $school->replicate();
        $other->diocesan_code = 'MESSAGE-OTHER';
        $other->official_name = 'Other recipient';
        $other->save();
        $this->actingAs($admin)->get(route('schools.message', $school))->assertOk()->assertSee('Message School')->assertDontSee('name="school_id"', false)->assertDontSee('<select', false);
        $this->post(route('schools.message.store', $school), ['school_id' => $other->id, 'subject' => 'Fixed recipient', 'message' => 'Selected school only.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notification_records', ['school_id' => $school->id, 'user_id' => $rep->id, 'subject' => 'Fixed recipient']);
        $this->assertDatabaseMissing('notification_records', ['school_id' => $other->id]);
        $this->actingAs($rep)->get(route('schools.message', $school))->assertForbidden();
    }

    public function test_representatives_cannot_send_and_other_schools_cannot_read_messages(): void
    {
        [$admin,$school,$rep] = $this->context();
        $this->actingAs($rep)->post(route('alerts.store'), ['school_id' => $school->id, 'subject' => 'No', 'message' => 'No'])->assertForbidden();
        $this->actingAs($admin)->post(route('alerts.store'), ['school_id' => $school->id, 'subject' => 'Private school message', 'message' => 'Only this school.'])->assertSessionHasNoErrors();
        $other = $school->replicate();
        $other->official_name = 'Other School';
        $other->diocesan_code = 'MESSAGE-2';
        $other->save();
        $reader = $this->representative($other);
        $this->actingAs($reader)->get(route('alerts.index'))->assertOk()->assertDontSee('Private school message')->assertViewHas('unreadCount', 0);
        $this->post(route('alerts.read', NotificationRecord::firstOrFail()))->assertForbidden();
    }

    public function test_school_scope_and_missing_recipients_are_enforced(): void
    {
        [$admin,$school,$rep] = $this->context();
        $role = Role::create(['slug' => 'message-reviewer', 'name' => 'Message reviewer']);
        $role->permissions()->sync(Permission::whereIn('slug', ['returns.review', 'schools.view.assigned'])->pluck('id'));
        $reviewer = User::factory()->create();
        $reviewer->roles()->attach($role);
        $this->actingAs($reviewer)->get(route('alerts.index', ['school_id' => $school->id]))->assertForbidden();
        $this->post(route('alerts.store'), ['school_id' => $school->id, 'subject' => 'No scope', 'message' => 'No scope'])->assertForbidden();
        $rep->update(['status' => 'suspended']);
        $this->actingAs($admin)->post(route('alerts.store'), ['school_id' => $school->id, 'subject' => 'No recipients', 'message' => 'Test'])->assertSessionHasErrors('school_id');
        $this->assertDatabaseCount('notification_records', 0);
    }
}
