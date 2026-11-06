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

        $this->assertDatabaseHas('parishes', ['name'=>'new parish','code'=>'MD-P0001']);
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

    public function test_parish_forms_fix_the_diocese_to_masaka_and_ignore_forged_values(): void
    {
        $admin = $this->superAdmin();
        $masaka = \App\Models\Diocese::where('code','MD')->firstOrFail();
        $other = \App\Models\Diocese::create(['name'=>'Other Diocese','code'=>'OTHER']);
        $this->actingAs($admin)->get(route('parishes.create'))->assertOk()->assertSee('Masaka Diocese')->assertDontSee('Select diocese');
        $this->post(route('parishes.store'),['name'=>'Fixed parish','code'=>'FIXED','diocese_id'=>$other->id,'is_active'=>1])->assertSessionHasNoErrors();
        $parish = Parish::where('name','Fixed parish')->firstOrFail();
        $this->assertSame($masaka->id,$parish->diocese_id);
        $this->put(route('parishes.update',$parish),['name'=>'Fixed updated','code'=>'FIXED','diocese_id'=>$other->id,'is_active'=>1])->assertSessionHasNoErrors();
        $this->assertSame($masaka->id,$parish->fresh()->diocese_id);
    }

    public function test_parish_codes_are_generated_sequentially_and_cannot_be_edited(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('parishes.store'), ['name'=>'Auto first','code'=>'FORGED','is_active'=>1])->assertSessionHasNoErrors();
        $first = Parish::where('name','Auto first')->firstOrFail();
        $this->assertSame('MD-P0001',$first->code);
        $this->post(route('parishes.store'), ['name'=>'Auto second','is_active'=>1])->assertSessionHasNoErrors();
        $this->assertSame('MD-P0002',Parish::where('name','Auto second')->firstOrFail()->code);
        $this->put(route('parishes.update',$first), ['name'=>'Auto renamed','code'=>'FORGED-EDIT','is_active'=>1])->assertSessionHasNoErrors();
        $this->assertSame('MD-P0001',$first->fresh()->code);
        $this->get(route('parishes.edit',$first))->assertOk()->assertSee('MD-P0001')->assertDontSee('name="code"',false);
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
