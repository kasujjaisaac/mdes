<?php

namespace Tests\Feature;

use App\Models\Parish;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdentityAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_seed_creates_required_roles_and_permissions(): void
    {
        $this->seed(IdentityAccessSeeder::class);

        $this->assertDatabaseHas('roles', ['slug' => 'system-super-administrator']);
        $this->assertDatabaseHas('roles', ['slug' => 'headteacher']);
        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseHas('permissions', ['slug' => 'users.manage']);
        $this->assertDatabaseHas('permissions', ['slug' => 'roles.view']);
    }

    public function test_super_admin_can_view_user_administration(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get('/administration/users')
            ->assertOk()
            ->assertSee('user account');
    }

    public function test_user_without_permission_cannot_access_user_administration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/administration/users')
            ->assertForbidden();
    }

    public function test_super_admin_can_create_user_with_role(): void
    {
        $admin = $this->superAdmin();
        $role = Role::query()->where('slug', 'headteacher')->firstOrFail();
        $this->seed(DiocesanStructureSeeder::class);
        $parish = Parish::firstOrFail();
        $school = School::create(['diocese_id' => $parish->diocese_id, 'parish_id' => $parish->id, 'official_name' => 'Assigned school', 'diocesan_code' => 'ASSIGNED-1', 'school_level' => 'primary', 'ownership' => 'other', 'operational_status' => 'active']);

        $this->actingAs($admin)
            ->post('/administration/users', [
                'school_id' => $school->id,
                'first_name' => 'Jane', 'last_name' => 'Headteacher',
                'email' => 'jane@example.test',
                'telephone' => '+256701111111',
                'designation' => 'Headteacher',
                'status' => User::STATUS_ACTIVE,
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'role_ids' => [$role->id],
            ])
            ->assertRedirect('/administration/users');

        $created = User::query()->where('email', 'jane@example.test')->firstOrFail();

        $this->assertTrue($created->roles()->where('slug', 'headteacher')->exists());
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.test',
            'status' => User::STATUS_SUSPENDED,
            'password' => Hash::make('secure-password'),
        ]);

        $this->from('/login')
            ->post('/login', [
                'username' => 'suspended',
                'password' => 'secure-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_suspended_authenticated_user_is_signed_out_from_protected_pages(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_SUSPENDED]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_successful_login_records_last_login_time(): void
    {
        $user = User::factory()->create([
            'email' => 'active@example.test',
            'status' => User::STATUS_ACTIVE,
            'password' => Hash::make('secure-password'),
        ]);

        $this->post('/login', [
            'username' => 'active',
            'password' => 'secure-password',
        ])->assertRedirect('/dashboard');

        $this->assertNotNull($user->refresh()->last_login_at);
    }

    private function superAdmin(): User
    {
        $this->seed(IdentityAccessSeeder::class);

        $role = Role::query()->where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail();

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->roles()->attach($role);

        return $user;
    }
}
