<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::table('reporting_periods',function(Blueprint $t){$t->boolean('is_archived')->default(false);$t->unsignedSmallInteger('annual_year')->nullable()->unique();});
  $periods=DB::table('reporting_periods')->where('type','annual_census')->get()->groupBy('year');
  foreach($periods as $year=>$group){
   $ordered=$group->sortByDesc(fn($p)=>DB::table('school_returns')->where('reporting_period_id',$p->id)->whereNotNull('submitted_at')->count());
   $primary=$ordered->first();
   DB::table('reporting_periods')->where('id',$primary->id)->update(['annual_year'=>$year]);
   foreach($ordered->skip(1) as $duplicate){
    DB::table('reporting_periods')->where('id',$duplicate->id)->update(['is_archived'=>true,'status'=>'closed']);
    DB::table('reporting_period_school')->where('reporting_period_id',$duplicate->id)->delete();
   }
  }
 }
 public function down():void {Schema::table('reporting_periods',function(Blueprint $t){$t->dropUnique(['annual_year']);$t->dropColumn(['annual_year','is_archived']);});}
};
