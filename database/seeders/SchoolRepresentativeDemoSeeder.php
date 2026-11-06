<?php
namespace Database\Seeders;
use App\Models\{Role,School,User,ReportingPeriod};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
class SchoolRepresentativeDemoSeeder extends Seeder {
 public function run():void {
  if(!app()->environment(['local','testing'])) throw new \RuntimeException('Demo representatives are restricted to local/testing environments.');
  $this->call(SchoolRepresentativeRoleSeeder::class);
  $role=Role::where('slug','school-representative')->firstOrFail();
  $schools=School::where('diocesan_code','like','DEMO-S%')->orderBy('diocesan_code')->limit(2)->get();
  if($schools->count()<2) throw new \RuntimeException('Run DashboardDemoSeeder first to create demo schools.');
  $period=ReportingPeriod::firstOrCreate(['type'=>'annual_census','annual_year'=>2026],['title'=>'DEMO 2026 Annual Collection','type'=>'annual_census','year'=>2026,'opening_date'=>'2026-01-01','closing_date'=>'2026-12-31','status'=>'open','created_by'=>User::where('email','admin@mdsiss.test')->value('id')??User::firstOrFail()->id,'instructions'=>'Demo school-representative workflow. Synthetic data only.']);
  $period->schools()->syncWithoutDetaching($schools->pluck('id'));
  foreach($schools as $i=>$school) {
   $email='representative'.($i+1).'@mdsiss.test';
   $user=User::where('email',$email)->first();
   if(!$user) {
    $password=Str::password(14,symbols:false);
    $user=User::create(['name'=>'Demo School Representative '.($i+1),'email'=>$email,'status'=>'active','school_id'=>$school->id,'password'=>$password,'designation'=>'School representative']);
    $user->roles()->sync([$role->id]);
    $this->command?->line($email.' | '.$password.' | '.$school->official_name);
   } else $this->command?->line($email.' already exists; password and assignment preserved.');
  }
 }
}
