<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Parish;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class UserAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(IdentityAccessSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());

        return $user;
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['first_name' => 'Created', 'last_name' => 'account', 'email' => 'new@example.test', 'status' => 'active', 'password' => 'Strong password 2026', 'password_confirmation' => 'Strong password 2026', 'role_ids' => [Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail()->id]], $changes);
    }

    public function test_creation_requires_role_strong_confirmed_password_and_normalizes_email(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->post(route('administration.users.store'), $this->payload(['role_ids' => [], 'password' => 'weak', 'password_confirmation' => 'different']))->assertSessionHasErrors(['role_ids', 'password']);
        $this->post(route('administration.users.store'), $this->payload(['email' => ' New@Example.Test ']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'first_name' => 'Created', 'last_name' => 'account', 'name' => 'Created account']);
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Strong password 2026', $user->password));
        $audit = AuditEvent::where('action', 'account.created')->firstOrFail();
        $this->assertStringNotContainsString('Strong password 2026', $audit->toJson());
        $this->assertArrayNotHasKey('password', $audit->new_values);
        $this->post(route('administration.users.store'), $this->payload(['email' => 'NEW@example.test']))->assertSessionHasErrors('email');
    }

    public function test_user_manager_cannot_assign_higher_privileges_or_edit_super_admin(): void
    {
        $admin = $this->admin();
        $role = Role::create(['slug' => 'limited-user-manager', 'name' => 'User manager']);
        $role->permissions()->sync(Permission::whereIn('slug', ['users.view', 'users.manage'])->pluck('id'));
        $manager = User::factory()->create();
        $manager->roles()->attach($role);
        $this->actingAs($manager)->post(route('administration.users.store'), $this->payload(['role_ids' => [Role::where('slug', Role::SUPER_ADMINISTRATOR)->first()->id]]))->assertForbidden();
        $this->get(route('administration.users.edit', $admin))->assertForbidden();
        $this->put(route('administration.users.update', $admin), $this->payload(['email' => $admin->email, 'role_ids' => [Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail()->id]]))->assertForbidden();
        $this->assertTrue($admin->fresh()->hasRole(Role::SUPER_ADMINISTRATOR));
    }

    public function test_self_deactivation_and_removing_last_active_admin_are_blocked(): void
    {
        $admin = $this->admin();
        $superId = Role::where('slug', Role::SUPER_ADMINISTRATOR)->first()->id;
        $this->actingAs($admin)->put(route('administration.users.update', $admin), $this->payload(['email' => $admin->email, 'status' => 'deactivated', 'role_ids' => [$superId]]))->assertSessionHasErrors('status');
        $this->seed(DiocesanStructureSeeder::class);
        $parish = Parish::firstOrFail();
        $school = School::create(['official_name' => 'Demotion target school', 'diocesan_code' => 'SELF-DEMOTION', 'diocese_id' => $parish->diocese_id, 'parish_id' => $parish->id, 'school_level' => 'primary', 'ownership' => 'other', 'operational_status' => 'active']);
        $this->put(route('administration.users.update', $admin), $this->payload(['email' => $admin->email, 'school_id' => $school->id, 'role_ids' => [Role::where('slug', 'headteacher')->firstOrFail()->id]]))->assertSessionHasErrors('status');
        $this->assertTrue($admin->fresh()->isActive());
        $this->assertTrue($admin->fresh()->hasRole(Role::SUPER_ADMINISTRATOR));
    }

    public function test_admin_password_reset_revokes_sessions_remember_login_and_reset_tokens(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['email' => 'target@example.test']);
        $target->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->first());
        $target->setRememberToken('old-remember-token');
        $target->save();
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $target->email, 'token' => 'old-token', 'created_at' => now()]);
        $this->actingAs($admin)->put(route('administration.users.update', $target), $this->payload(['email' => $target->email]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertNotSame('old-remember-token', $target->fresh()->getRememberToken());
        $this->assertTrue(Hash::check('Strong password 2026', $target->fresh()->password));
        $audit = AuditEvent::where('action', 'account.updated')->firstOrFail();
        $this->assertTrue($audit->new_values['password_changed']);
        $this->assertStringNotContainsString('Strong password 2026', $audit->toJson());
    }

    public function test_blank_password_keeps_existing_password_and_deactivation_blocks_login(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['email' => 'disabled@example.test', 'password' => 'Original password 2026']);
        $hash = $target->password;
        $this->actingAs($admin)->put(route('administration.users.update', $target), $this->payload(['email' => $target->email, 'status' => 'deactivated', 'password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
        $this->assertSame($hash, $target->fresh()->password);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['username' => $target->username, 'password' => 'Original password 2026'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_normalizes_email_and_throttles_repeated_failures(): void
    {
        $user = User::factory()->create(['email' => 'case@example.test', 'password' => 'Correct password 2026']);
        $this->post(route('login.store'), ['username' => ' CASE', 'password' => 'Correct password 2026'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));
        RateLimiter::clear('case|127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['username' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        }
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'Correct password 2026'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_no_hard_delete_endpoint_is_exposed(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($admin)->delete('/administration/users/'.$target->id)->assertStatus(405);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_edit_preserves_separate_name_parts_and_requires_both_names(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Legacy User Name']);
        $target->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $this->actingAs($admin)->get(route('administration.users.edit', $target))->assertOk()->assertSee('Back to users')->assertSee('name="first_name"', false)->assertSee('name="last_name"', false);
        $this->put(route('administration.users.update', $target), $this->payload(['email' => $target->email, 'first_name' => 'Mary Jane', 'last_name' => 'Namusoke', 'password' => '', 'password_confirmation' => '']))->assertSessionHasNoErrors();
        $this->assertSame('Mary Jane', $target->fresh()->first_name);
        $this->assertSame('Namusoke', $target->fresh()->last_name);
        $this->assertSame('Mary Jane Namusoke', $target->fresh()->name);
        $this->get(route('administration.users.edit', $target))->assertSee('value="Mary Jane"', false)->assertSee('value="Namusoke"', false);
        $this->post(route('administration.users.store'), $this->payload(['first_name' => '', 'last_name' => '']))->assertSessionHasErrors(['first_name', 'last_name']);
    }
}
