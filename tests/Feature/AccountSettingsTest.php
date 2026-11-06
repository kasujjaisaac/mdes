<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\IdentityAccessSeeder;
use Database\Seeders\SchoolRepresentativeRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_contains_permission_scoped_submenus(): void
    {
        $this->seed([IdentityAccessSeeder::class, SchoolRepresentativeRoleSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $this->actingAs($admin)->get(route('profile.password.edit'))->assertOk()->assertSee('settings-menu-desktop')->assertSee('Users')->assertSee('Window settings')->assertSee('Change password');
        $rep = User::factory()->create();
        $rep->roles()->attach(Role::where('slug', 'headteacher')->firstOrFail());
        $this->actingAs($rep)->get(route('profile.password.edit'))->assertOk()->assertSee('Change password')->assertDontSee('Window settings')->assertDontSee(route('administration.users.index'));
        $this->get(route('administration.users.index'))->assertForbidden();
    }

    public function test_password_change_requires_current_password_and_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'Current password 2026']);
        $hash = $user->password;
        $this->actingAs($user)->put(route('profile.password.update'), ['current_password' => 'Wrong current password', 'password' => 'New strong password 2026', 'password_confirmation' => 'New strong password 2026'])->assertSessionHasErrors('current_password');
        $this->assertSame($hash, $user->fresh()->password);
        $this->put(route('profile.password.update'), ['current_password' => 'Current password 2026', 'password' => 'New strong password 2026', 'password_confirmation' => 'Different password'])->assertSessionHasErrors('password');
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_password_change_updates_only_the_signed_in_account_and_audits_no_secrets(): void
    {
        $user = User::factory()->create(['password' => 'Current password 2026']);
        $other = User::factory()->create(['password' => 'Other password 2026']);
        $this->actingAs($user)->put(route('profile.password.update'), ['user_id' => $other->id, 'current_password' => 'Current password 2026', 'password' => 'New strong password 2026', 'password_confirmation' => 'New strong password 2026'])->assertSessionHasNoErrors()->assertRedirect(route('profile.password.edit'));
        $this->assertTrue(Hash::check('New strong password 2026', $user->fresh()->password));
        $this->assertTrue(Hash::check('Other password 2026', $other->fresh()->password));
        $this->assertAuthenticatedAs($user);
        $audit = AuditEvent::where('action', 'account.password_changed')->firstOrFail();
        $this->assertSame(['password_changed' => true], $audit->new_values);
        $this->assertStringNotContainsString('New strong password 2026', $audit->toJson());
    }

    public function test_password_settings_require_login(): void
    {
        $this->get(route('profile.password.edit'))->assertRedirect(route('login'));
        $this->put(route('profile.password.update'), [])->assertRedirect(route('login'));
    }
}
