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

    public function test_required_school_registration_details_are_enforced_even_if_wizard_is_bypassed(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $data = $this->payload();
        $this->get(route('schools.register'))->assertOk()->assertSee('data-registration-wizard', false)->assertSee('Leadership')->assertDontSee('mdes123');
        foreach (array_diff(array_keys($data), ['first_deputy_name', 'first_deputy_phone', 'second_deputy_name', 'second_deputy_phone']) as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->post(route('schools.register.store'), $invalid)->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('schools', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_school_can_register_without_either_deputy(): void
    {
        $data = $this->payload();
        foreach (['first_deputy_name', 'first_deputy_phone', 'second_deputy_name', 'second_deputy_phone'] as $field) {
            unset($data[$field]);
        }
        $this->post(route('schools.register.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $school = User::where('email', $data['email'])->firstOrFail()->school;
        $this->assertDatabaseMissing('school_leadership_histories', ['school_id' => $school->id, 'position' => 'first_deputy']);
        $this->assertDatabaseMissing('school_leadership_histories', ['school_id' => $school->id, 'position' => 'second_deputy']);
    }

    public function test_credentials_are_shown_only_after_success_and_email_is_captured_with_headteacher(): void
    {
        $data = $this->payload();
        $page = $this->get(route('schools.register'))->assertOk()->assertDontSee('Your sign-in details')->assertDontSee('mdes123');
        $html = $page->getContent();
        $this->assertSame(3, substr_count($html, '<fieldset class="registration-step"'));
        $this->assertLessThan(strpos($html, '1st Deputy Headteacher'), strpos($html, 'for="register-email"'));
        $this->post(route('schools.register.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Registration successful')->assertSee('data-registration-success', false)
            ->assertSee($data['ministry_registration_number'])->assertSee('mdes123')->assertSee('Continue to sign in');
        $this->get(route('login'))->assertOk()->assertDontSee('data-registration-success', false)->assertDontSee('mdes123');
    }

    public function test_deputy_names_can_be_supplied_without_phone_numbers(): void
    {
        $data = $this->payload();
        $data['first_deputy_phone'] = '';
        $data['second_deputy_phone'] = '';
        $this->post(route('schools.register.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $school = User::where('email', $data['email'])->firstOrFail()->school;
        $this->assertDatabaseHas('school_leadership_histories', ['school_id' => $school->id, 'position' => 'first_deputy', 'person_name' => $data['first_deputy_name'], 'telephone' => null]);
        $this->assertDatabaseHas('school_leadership_histories', ['school_id' => $school->id, 'position' => 'second_deputy', 'person_name' => $data['second_deputy_name'], 'telephone' => null]);
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
