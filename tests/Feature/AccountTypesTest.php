<?php

namespace Tests\Feature;

use App\Models\Parish;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTypesTest extends TestCase
{
    use RefreshDatabase;

    private function setupAccount(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $parish = Parish::firstOrFail();
        $school = School::create(['official_name' => 'Headteacher School', 'diocesan_code' => 'TWO-ROLES', 'diocese_id' => $parish->diocese_id, 'parish_id' => $parish->id, 'school_level' => 'primary', 'ownership' => 'other', 'operational_status' => 'active']);

        return [$admin, $school];
    }

    private function payload(): array
    {
        return ['first_name' => 'School', 'last_name' => 'Head', 'email' => 'head@example.test', 'status' => 'active', 'password' => 'Strong school password 2026', 'password_confirmation' => 'Strong school password 2026', 'role_ids' => [Role::where('slug', Role::HEADTEACHER)->firstOrFail()->id]];
    }

    public function test_only_two_account_types_are_offered_and_a_school_is_required_for_headteacher(): void
    {
        [$admin,$school] = $this->setupAccount();
        $this->assertDatabaseCount('roles', 2);
        $this->actingAs($admin)->get(route('administration.users.create'))->assertOk()->assertSee('School Headteacher')->assertSee('Admin')->assertDontSee('name="designation"', false);
        $this->post(route('administration.users.store'), $this->payload())->assertSessionHasErrors('school_id');
        $this->post(route('administration.users.store'), $this->payload() + ['school_id' => $school->id, 'designation' => 'Inspector'])->assertSessionHasNoErrors();
        $head = User::where('email', 'head@example.test')->firstOrFail();
        $this->assertSame('School Headteacher', $head->designation);
        $this->assertTrue($head->isSchoolRepresentative());
        $this->assertFalse($head->hasPermission('users.manage'));
    }

    public function test_other_roles_multiple_roles_and_school_assigned_admin_are_rejected(): void
    {
        [$admin,$school] = $this->setupAccount();
        $legacy = Role::create(['slug' => 'inspector', 'name' => 'Inspector']);
        $data = $this->payload();
        $this->actingAs($admin)->post(route('administration.users.store'), array_replace($data, ['role_ids' => [$legacy->id]]))->assertSessionHasErrors('role_ids.0');
        $super = Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail()->id;
        $this->post(route('administration.users.store'), array_replace($data, ['role_ids' => [$super, $data['role_ids'][0]], 'school_id' => $school->id]))->assertSessionHasErrors('role_ids');
        $this->post(route('administration.users.store'), array_replace($data, ['role_ids' => [$super], 'school_id' => $school->id]))->assertSessionHasErrors('school_id');
    }

    public function test_migration_maps_existing_school_account_and_preserves_account_identity(): void
    {
        [$admin,$school] = $this->setupAccount();
        $legacy = Role::create(['slug' => 'school-representative', 'name' => 'School Representative']);
        $user = User::factory()->create(['school_id' => $school->id]);
        $user->roles()->attach($legacy);
        $hash = $user->password;
        $migration = require database_path('migrations/2026_11_06_000005_limit_account_types.php');
        $migration->up();
        $this->assertDatabaseCount('roles', 2);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame($school->id, $user->fresh()->school_id);
        $this->assertTrue($user->fresh()->hasRole('headteacher'));
        $this->assertTrue($admin->fresh()->hasRole(Role::SUPER_ADMINISTRATOR));
    }

    public function test_school_moe_number_cannot_claim_an_existing_admin_username(): void
    {
        [$admin,$school] = $this->setupAccount();
        $this->actingAs($admin)->post(route('schools.store'), ['official_name' => 'Username conflict school', 'parish_id' => $school->parish_id, 'school_level' => 'primary', 'ministry_registration_number' => $admin->username])->assertSessionHasErrors('ministry_registration_number');
        $this->assertDatabaseMissing('schools', ['official_name' => 'Username conflict school']);
    }
}
