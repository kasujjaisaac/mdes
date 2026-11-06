<?php

namespace Tests\Feature;

use App\Models\Diocese;
use App\Models\JurisdictionAssignment;
use App\Models\Parish;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiocesanStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_parish(): void
    {
        $admin = $this->superAdmin();
        $diocese = Diocese::query()->firstOrFail();

        $this->actingAs($admin)
            ->post('/administration/diocesan-structure/parishes', [
                'diocese_id' => $diocese->id,
                'name' => 'new parish',
                'code' => 'NEW',
                'is_active' => 1,
            ])
            ->assertRedirect('/administration/diocesan-structure');

        $this->assertDatabaseHas('parishes', ['code' => 'NEW']);
    }

    public function test_unauthorized_user_cannot_manage_diocesan_structure(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/administration/diocesan-structure')
            ->assertForbidden();
    }

    public function test_jurisdiction_assignment_is_effective_dated(): void
    {
        $admin = $this->superAdmin();
        $coordinator = User::factory()->create();
        $parish = Parish::query()->firstOrFail();

        $this->actingAs($admin)
            ->post('/administration/diocesan-structure/assignments', [
                'user_id' => $coordinator->id,
                'parish_id' => $parish->id,
                'assignment_type' => JurisdictionAssignment::TYPE_PARISH_COORDINATOR,
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'is_active' => 1,
            ])
            ->assertRedirect('/administration/diocesan-structure');

        $this->assertDatabaseHas('jurisdiction_assignments', [
            'user_id' => $coordinator->id,
            'parish_id' => $parish->id,
            'assignment_type' => JurisdictionAssignment::TYPE_PARISH_COORDINATOR,
        ]);
    }

    public function test_parish_module_lists_searches_and_updates_parishes(): void
    {
        $admin=$this->superAdmin();
        $this->actingAs($admin)->get(route('parishes.index'))->assertOk()->assertSee('Add parish');
        $this->get(route('parishes.index',['search'=>'KIT']))->assertOk()->assertViewHas('parishes',fn($rows)=>$rows->total()===1);
        $parish=Parish::where('code','KIT')->firstOrFail();
        $this->get(route('parishes.edit',$parish))->assertOk();
        $this->put(route('parishes.update',$parish),['diocese_id'=>$parish->diocese_id,'name'=>'Updated parish','code'=>'KIT','is_active'=>1])->assertRedirect(route('parishes.index'));
        $this->assertSame('Updated parish',$parish->fresh()->name);
        $this->actingAs(User::factory()->create())->get(route('parishes.index'))->assertForbidden();
    }

    private function superAdmin(): User
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);

        $role = Role::query()->where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
