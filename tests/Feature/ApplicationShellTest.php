<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_application_shell_dashboard(): void
    {
        $this->seed(IdentityAccessSeeder::class);
        $user = User::factory()->create(['name' => 'MDSISS Admin']);
        $user->roles()->attach(Role::query()->where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('MDSISS')
            ->assertSee('Users')
            ->assertSee('Registered Schools')
            ->assertDontSee('Inspections')
            ->assertDontSee('Financial Statistics')
            ->assertSee('Settings')->assertSee('Change password')->assertSee('Window settings')
            ->assertDontSee('Diocesan Structure')
            ->assertDontSee('Roles &amp; Permissions', false)
            ->assertDontSee('Reference Data')
            ->assertDontSee('Intelligence')
            ->assertDontSee('Parish comparison')
            ->assertDontSee('Schools needing attention');
    }

    public function test_reference_data_page_is_available_to_authorized_users(): void
    {
        $this->seed(IdentityAccessSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());

        $this->actingAs($user)
            ->get('/administration/reference-data')
            ->assertOk()
            ->assertSee('reference data')
            ->assertSee('controlled values');
    }

    public function test_application_shell_requires_authentication(): void
    {
        $this->get('/administration/reference-data')->assertRedirect('/login');
    }
}
