<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Parish;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Database\Seeders\SchoolRepresentativeRoleSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class, SchoolRepresentativeRoleSeeder::class]);

        return ['registration_date' => now('Africa/Kampala')->toDateString(), 'emis_number' => 'EMIS-SELF-001', 'first_deputy_name' => 'First Deputy', 'first_deputy_phone' => '0700111111', 'second_deputy_name' => 'Second Deputy', 'second_deputy_phone' => '0700222222', 'official_name' => 'Self Registered School', 'headteacher_name' => 'Registered Headteacher', 'parish_id' => Parish::first()->id, 'district_id' => District::first()->id,
            'ministry_registration_number' => 'MOE-SELF-001', 'school_level' => 'primary', 'funding_classification' => 'Private', 'gender_classification' => 'Mixed', 'boarding_status' => 'Day',
            'name' => 'School Representative', 'telephone' => '0700000000', 'email' => 'school@example.test', 'password' => 'A long school password 2026', 'password_confirmation' => 'A long school password 2026'];
    }

    public function test_school_can_register_a_profile_and_scoped_account_without_a_return(): void
    {
        $data = $this->payload();
        $this->get(route('schools.register'))->assertOk()->assertSee('Register your school');
        $this->post(route('schools.register.store'), $data + ['status' => 'suspended', 'roles' => ['super-administrator'], 'school_id' => 999, 'diocese_id' => 999])
            ->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $school = School::where('official_name', $data['official_name'])->firstOrFail();
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertGuest();
        $this->assertSame($school->id, $user->school_id);
        $this->assertTrue($user->isSchoolRepresentative());
        $this->assertFalse($user->hasPermission('schools.view.all'));
        $this->assertFalse($user->hasPermission('users.manage'));
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('mdes123', $user->password));
        $this->assertSame($data['email'], $school->contact->email);
        $this->assertDatabaseCount('school_returns', 0);
        $this->assertDatabaseCount('reporting_period_school', 0);
        $this->assertDatabaseHas('audit_events', ['action' => 'school.self_registered', 'user_id' => $user->id]);
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseHas('school_leadership_histories', ['school_id' => $school->id, 'position' => 'first_deputy', 'person_name' => $data['first_deputy_name'], 'telephone' => $data['first_deputy_phone']]);
        $this->assertDatabaseHas('school_leadership_histories', ['school_id' => $school->id, 'position' => 'second_deputy', 'person_name' => $data['second_deputy_name']]);
        $this->assertSame($data['telephone'], $school->currentHeadteacher->telephone);
        $this->get(route('representative.profile'))->assertRedirect(route('login'));
    }

    public function test_duplicate_school_cannot_create_another_account_or_claim_existing_profile(): void
    {
        $data = $this->payload();
        $this->post(route('schools.register.store'), $data)->assertSessionHasNoErrors();
        Auth::logout();
        $data['email'] = 'another@example.test';
        $data['ministry_registration_number'] = 'MOE-DIFFERENT-002';
        $data['emis_number'] = 'EMIS-DIFFERENT-002';
        $data['official_name'] = strtoupper($data['official_name']);
        $this->post(route('schools.register.store'), $data)->assertSessionHasErrors('official_name');
        $this->assertDatabaseCount('schools', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'another@example.test']);
    }

    public function test_invalid_profile_and_password_do_not_create_partial_registration(): void
    {
        $data = $this->payload();
        $data['emis_number'] = '';
        $data['parish_id'] = 999999;
        $this->post(route('schools.register.store'), $data)->assertSessionHasErrors(['emis_number', 'parish_id']);
        $this->assertDatabaseCount('schools', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('school_returns', 0);
    }

    public function test_moe_number_is_required_and_duplicate_numbers_are_rejected_across_names_and_parishes(): void
    {
        $data = $this->payload();
        $missing = $data;
        unset($missing['ministry_registration_number']);
        $this->post(route('schools.register.store'), $missing)->assertSessionHasErrors('ministry_registration_number');
        $this->post(route('schools.register.store'), [...$data, 'ministry_registration_number' => ' moe- self-001 '])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('schools', ['ministry_registration_number' => 'MOE-SELF-001']);
        Auth::logout();
        $this->post(route('schools.register.store'), [...$data, 'official_name' => 'Different school name', 'parish_id' => Parish::where('id', '!=', $data['parish_id'])->firstOrFail()->id, 'email' => 'duplicate-moe@example.test', 'ministry_registration_number' => 'moe-self-001'])->assertSessionHasErrors('ministry_registration_number');
        $this->assertDatabaseCount('schools', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_cannot_assign_second_account_even_when_existing_account_is_suspended(): void
    {
        $data = $this->payload();
        $this->post(route('schools.register.store'), $data)->assertSessionHasNoErrors();
        $schoolUser = User::where('email', $data['email'])->firstOrFail();
        $schoolUser->update(['status' => 'suspended']);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $roleId = Role::where('slug', 'headteacher')->firstOrFail()->id;
        $account = ['first_name' => 'Replacement', 'last_name' => 'Headteacher', 'email' => 'replacement@example.test', 'password' => 'Replacement password 2026', 'password_confirmation' => 'Replacement password 2026', 'status' => 'active', 'school_id' => $schoolUser->school_id, 'role_ids' => [$roleId]];
        $this->actingAs($admin)->post(route('administration.users.store'), $account)->assertSessionHasErrors('school_id');
        $otherUser = User::factory()->create();
        $this->put(route('administration.users.update', $otherUser), $account)->assertSessionHasErrors('school_id');
        $this->put(route('administration.users.update', $schoolUser), [...$account, 'email' => $schoolUser->email])->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('school_id', $schoolUser->school_id)->count());
    }

    public function test_database_prevents_multiple_accounts_for_same_school(): void
    {
        $data = $this->payload();
        $this->post(route('schools.register.store'), $data)->assertSessionHasNoErrors();
        $school = School::firstOrFail();
        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['school_id' => $school->id]);
    }
}
