<?php

namespace Tests\Feature;

use App\Models\{Parish, Permission, ReportingPeriod, Role, School, SchoolReturn, User};
use Database\Seeders\{DiocesanStructureSeeder, IdentityAccessSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBoundaryAuditTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug',Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $parish = Parish::firstOrFail();
        $school = School::create(['official_name'=>'Role boundary school','diocesan_code'=>'ROLE-01','ministry_registration_number'=>'ROLE-01','parish_id'=>$parish->id,'diocese_id'=>$parish->diocese_id,'school_level'=>'primary','operational_status'=>'active','ownership'=>'other']);
        $head = User::factory()->create(['school_id'=>$school->id]);
        $head->roles()->attach(Role::where('slug',Role::HEADTEACHER)->firstOrFail());
        $period = ReportingPeriod::create(['title'=>'Role boundary collection','type'=>'annual_census','year'=>2026,'opening_date'=>'2026-01-01','closing_date'=>'2026-12-31','status'=>'open','created_by'=>$admin->id]);
        $return = SchoolReturn::create(['school_id'=>$school->id,'reporting_period_id'=>$period->id,'status'=>'draft']);
        return [$admin,$head,$school,$period,$return];
    }

    public function test_extra_role_permissions_cannot_turn_a_headteacher_into_an_admin(): void
    {
        [$admin,$head,$school,$period,$return] = $this->context();
        Role::where('slug',Role::HEADTEACHER)->firstOrFail()->permissions()->sync(Permission::pluck('id'));
        $head->unsetRelation('roles');
        $this->assertTrue($head->isSchoolRepresentative());
        $this->assertFalse($head->isAdministrator());
        foreach (['schools.view.all','users.manage','returns.review','reporting-periods.view','communications.manage','reports.view','settings.view'] as $permission) {
            $this->assertFalse($head->hasPermission($permission),$permission);
        }
        $this->actingAs($head)->get(route('representative.home'))->assertOk()
            ->assertDontSee('href="'.route('administration.users.index').'"',false)
            ->assertDontSee('href="'.route('reporting-periods.index').'"',false);
        foreach (['administration.users.index','administration.users.create','administration.roles.index','reference-data.index','parishes.index','schools.index','schools.create','reporting-periods.index','reporting-periods.create','reports.index','analytics.index','audit-trail.index','settings.index','logs.index','announcements.create'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('announcements.publish'),[])->assertForbidden();
        $this->post(route('returns.transition',$return),['action'=>'approve'])->assertForbidden();
        $other = $school->replicate(); $other->diocesan_code='ROLE-02'; $other->ministry_registration_number='ROLE-02'; $other->save();
        $foreign = SchoolReturn::create(['school_id'=>$other->id,'reporting_period_id'=>$period->id,'status'=>'draft']);
        $this->get(route('returns.show',$foreign))->assertForbidden();
        $this->assertSame([$school->id],School::visibleTo($head)->pluck('id')->all());
    }

    public function test_mixed_school_and_admin_roles_never_get_the_admin_bypass(): void
    {
        [$admin,$head,$school,$period,$return] = $this->context();
        $head->roles()->attach(Role::where('slug',Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $head->unsetRelation('roles');
        $this->assertFalse($head->isAdministrator());
        $this->assertFalse($head->hasPermission('users.manage'));
        $this->actingAs($head)->get(route('administration.users.index'))->assertForbidden();
        $this->post(route('returns.transition',$return),['action'=>'approve'])->assertForbidden();
        $this->get(route('representative.profile'))->assertForbidden();
    }

    public function test_admin_can_manage_but_cannot_write_school_answers_or_enter_school_pages(): void
    {
        [$admin,$head,$school,$period,$return] = $this->context();
        $this->assertTrue($admin->isAdministrator());
        $this->assertFalse($admin->isSchoolRepresentative());
        $this->actingAs($admin)->get(route('administration.users.index'))->assertOk();
        $this->get(route('schools.index'))->assertOk();
        $this->get(route('reporting-periods.index'))->assertOk();
        $this->get(route('representative.home'))->assertForbidden();
        $this->get(route('representative.profile.edit'))->assertForbidden();
        $this->post(route('returns.form.save',[$return,'identity']),['data'=>['school_name'=>'Admin rewrite']])->assertForbidden();
        $this->postJson(route('returns.form.autosave',[$return,'identity']),['revision'=>0,'data'=>[]])->assertForbidden();
    }

    public function test_unsupported_roles_cannot_grant_admin_access(): void
    {
        [$admin] = $this->context();
        $legacy = Role::create(['slug'=>'old-manager','name'=>'Old manager']);
        $legacy->permissions()->sync(Permission::pluck('id'));
        $user = User::factory()->create(); $user->roles()->attach($legacy);
        $this->assertFalse($user->hasPermission('users.manage'));
        $this->assertSame(0,School::visibleTo($user)->count());
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->get(route('administration.users.index'))->assertForbidden();
    }

    public function test_corrupt_role_grants_do_not_allow_admin_self_demotion_or_count_as_valid_admins(): void
    {
        [$admin,$head,$school] = $this->context();
        Role::where('slug',Role::HEADTEACHER)->firstOrFail()->permissions()->syncWithoutDetaching(Permission::where('slug','users.manage')->pluck('id'));
        $head->roles()->attach(Role::where('slug',Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $this->assertSame([$admin->id],User::administrators()->pluck('id')->all());
        $other = $school->replicate(); $other->diocesan_code='SELF-DEMOTE'; $other->ministry_registration_number='SELF-DEMOTE'; $other->save();
        $this->actingAs($admin)->put(route('administration.users.update',$admin),[
            'first_name'=>'Current','last_name'=>'Admin','email'=>$admin->email,'status'=>'active',
            'school_id'=>$other->id,'role_ids'=>[Role::where('slug',Role::HEADTEACHER)->firstOrFail()->id],
        ])->assertSessionHasErrors('status');
        $this->assertTrue($admin->fresh()->isAdministrator());
    }
}
