<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Parish;
use App\Models\User;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SchoolRegistrationWizardTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);

        return ['official_name' => 'New School Profile', 'ministry_registration_number' => 'MOE/NEW-001', 'emis_number' => 'EMIS-NEW-001', 'parish_id' => Parish::first()->id, 'district_id' => District::first()->id, 'school_level' => 'primary', 'funding_classification' => 'Government', 'gender_classification' => 'Mixed', 'boarding_status' => 'Day', 'headteacher_name' => 'Mary School Head', 'telephone' => '0700000001', 'first_deputy_name' => 'First School Deputy', 'first_deputy_phone' => '0700000002', 'second_deputy_name' => 'Second School Deputy', 'second_deputy_phone' => '0700000003', 'email' => 'new-head@example.test'];
    }

    public function test_all_school_registration_details_are_required_even_if_wizard_is_bypassed(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $data = $this->payload();
        $this->get(route('schools.register'))->assertOk()->assertSee('data-registration-wizard', false)->assertSee('Leadership')->assertSee('mdes123');
        foreach (array_keys($data) as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->post(route('schools.register.store'), $invalid)->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('schools', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_generated_login_allows_school_access_and_password_change_is_optional(): void
    {
        $data = $this->payload();
        $this->post(route('schools.register.store'), $data + ['password' => 'InjectedPassword!2026', 'must_change_password' => true])->assertRedirect(route('login'))->assertSessionHas('registered_username', $data['ministry_registration_number']);
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertTrue(Hash::check('mdes123', $user->password));
        $this->assertSame(now('Africa/Kampala')->toDateString(), $user->school->registration_date->toDateString());
        $this->assertFalse($user->must_change_password);
        $this->assertGuest();
        $this->post(route('login.store'), ['username' => ' moe/new-001 ', 'password' => 'mdes123'])->assertSessionHasNoErrors()->assertRedirect(route('representative.home'));
        foreach (['representative.home', 'representative.profile', 'announcements.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('profile.password.edit'))->assertOk()->assertViewIs('system.change-password');
        $strong = 'Strong!Headteacher2026';
        $this->put(route('profile.password.update'), ['password' => $strong, 'password_confirmation' => $strong])->assertSessionHasErrors('current_password');
        $this->put(route('profile.password.update'), ['current_password' => 'mdes123', 'password' => $strong, 'password_confirmation' => $strong])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($strong, $user->fresh()->password));
        $this->post(route('logout'));
        $this->post(route('login.store'), ['username' => $data['ministry_registration_number'], 'password' => $strong])->assertSessionHasNoErrors()->assertRedirect(route('representative.home'));
    }

    public function test_password_recovery_cannot_bypass_strong_initial_password_rules(): void
    {
        $data = $this->payload();
        $this->post(route('schools.register.store'), $data)->assertSessionHasNoErrors();
        $user = User::where('email', $data['email'])->firstOrFail();
        $token = Password::createToken($user);
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => 'weakpassword2026', 'password_confirmation' => 'weakpassword2026'])->assertSessionHasErrors('password');
        $this->assertFalse($user->fresh()->must_change_password);
        $strong = 'Recovered!Password2026';
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => $strong, 'password_confirmation' => $strong])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
