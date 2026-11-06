<?php
namespace Database\Seeders;
use App\Models\{Role,Permission};
use Illuminate\Database\Seeder;
class SchoolRepresentativeRoleSeeder extends Seeder {
 public function run():void {
  $role=Role::firstOrCreate(['slug'=>'school-representative'],['name'=>'School Representative','description'=>'Enters and submits forms for one directly assigned school.']);
  $role->permissions()->sync(Permission::whereIn('slug',['dashboard.view','schools.view.assigned','returns.view','returns.manage.assigned','reporting-periods.view'])->pluck('id'));
 }
}
