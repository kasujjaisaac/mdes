<?php
namespace Database\Seeders;

use App\Models\{Diocese,District,Parish,ReportingPeriod,School,SchoolReturn,User};
use App\Services\StatisticalFormSummaryService;
use App\Support\StatisticalForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local','testing'])) throw new \RuntimeException('Demo data may only be seeded in local or testing environments.');
        $user=User::where('email','admin@mdsiss.test')->first() ?? User::first();
        if(!$user) throw new \RuntimeException('Create an administrator before adding demo data.');
        DB::transaction(function() use($user) {
            $diocese=Diocese::firstOrCreate(['code'=>'DEMO-MD'],['name'=>'Demo Diocese','is_active'=>true]);
            $district=District::firstOrCreate(['code'=>'DEMO-D'],['name'=>'Demo District','is_active'=>true]);
            $parishes=collect(['Central','Lakeside','Hilltop','Valley'])->map(fn($name,$i)=>Parish::firstOrCreate(['code'=>'DEMO-P'.$i],['diocese_id'=>$diocese->id,'name'=>'Demo '.$name.' Parish','is_active'=>true]));
            $schools=collect();
            for($i=0;$i<12;$i++) {
                $schools->push(School::firstOrCreate(['diocesan_code'=>'DEMO-S'.str_pad((string)$i,3,'0',STR_PAD_LEFT)],[
                    'diocese_id'=>$diocese->id,'parish_id'=>$parishes[$i%4]->id,'district_id'=>$district->id,
                    'official_name'=>'Demo '.['St Mark','St Clare','St Luke','Holy Family','St Anne','St Peter','St Paul','St Mary','St Joseph','St Agnes','Good Shepherd','St Francis'][$i].' School',
                    'school_level'=>['primary','secondary','nursery','primary','vocational_technical','secondary','primary','tertiary','primary','secondary','nursery','primary'][$i],
                    'ownership'=>'other','funding_classification'=>$i%3?'Government':'Private','gender_classification'=>'Mixed','boarding_status'=>['Day','Boarding','Both day and boarding'][$i%3],'operational_status'=>'active',
                ]));
            }
            foreach([2024,2025,2026] as $year) {
                $period=ReportingPeriod::firstOrCreate(['annual_year'=>$year],['title'=>'DEMO '.$year.' Annual Collection','year'=>$year,'type'=>'annual_census','opening_date'=>"$year-01-01",'closing_date'=>"$year-12-31",'status'=>$year===2026?'open':'closed','created_by'=>$user->id,'instructions'=>'SYNTHETIC DEMO DATA. Not official school statistics.']);
                $period->schools()->syncWithoutDetaching($schools->pluck('id'));
                foreach($schools as $i=>$school) {
                    if($year===2026 && $i>=10) continue;
                    if(SchoolReturn::where('school_id',$school->id)->where('reporting_period_id',$period->id)->exists()) continue;
                    $form=array_fill_keys(array_keys(StatisticalForm::sections()),[]);
                    $form['identity']=['date'=>"$year-03-01",'school_name'=>$school->official_name,'registration_number'=>'DEMO-REG-'.$i,'emis_code'=>'DEMO-EMIS-'.$i,'parish'=>$parishes[$i%4]->name,'district'=>$district->name,'headteacher_name'=>'Demo Headteacher '.$i,'level'=>match($school->school_level){'nursery'=>'Pre-primary','primary'=>'Primary','secondary'=>'Secondary','tertiary'=>'Tertiary',default=>'Vocational/Technical'},'funding'=>$school->funding_classification,'gender'=>'Mixed','boarding'=>$school->boarding_status];
                    $group=$school->school_level==='nursery'?'nursery':'classes';
                    $labels=$group==='nursery'?['baby','middle','top']:array_map(fn($n)=>'year_'.$n,range(1,$school->school_level==='secondary'?6:7));
                    foreach($labels as $j=>$label) $form['enrolment'][$group][$label]=['boys'=>15+$i*3+$j*2+($year-2024)*4,'girls'=>18+$i*2+$j+($year-2024)*5];
                    $learners=collect($form['enrolment'][$group])->sum(fn($r)=>$r['boys']+$r['girls']);
                    $teaching=[];
                    for($j=0;$j<8+$i;$j++) $teaching[]=['name'=>'Demo Teacher '.($i+1).'-'.($j+1),'gender'=>$j%2?'Female':'Male','payroll'=>$j%3?'Government':'Private','age'=>25+$j,'qualification'=>['Diploma','Bachelors degree','Certificate','Masters degree'][$j%4],'religion'=>['Catholic','Anglican','Muslim','Other'][$j%4],'responsibility'=>$j===0?'Headteacher':'Class teacher','license_number'=>'DEMO-T-'.$i.'-'.$j];
                    $form['teaching']['members']=$teaching;
                    $form['support']['members']=array_map(fn($j)=>['name'=>'Demo Support '.$i.'-'.$j,'gender'=>$j%2?'Female':'Male','payroll'=>'Private','age'=>27+$j,'qualification'=>'Certificate','religion'=>'Catholic','responsibility'=>['Cook','Caretaker','Clerk'][$j%3]],range(1,3+$i%4));
                    foreach(['learners'=>$learners,'teachers'=>count($teaching),'support_staff'=>count($form['support']['members'])] as $key=>$total) {
                        $a=(int)floor($total*.55);$b=(int)floor($total*.25);$c=(int)floor($total*.15);
                        $form['religion']['community'][$key]=['catholics'=>$a,'anglicans'=>$b,'muslims'=>$c,'others'=>$total-$a-$b-$c];
                    }
                    $form['utilities']=['water_sources'=>[$i%2?'Borehole':'National Water Supply'],'water_reliable'=>$i%4?'Yes':'No','water_explanation'=>$i%4?'Demo: reliable supply':'Demo: seasonal interruptions','power_sources'=>[$i%2?'Solar system':'Hydro-electricity (national grid)']];
                    foreach(StatisticalForm::sections()['facilities']['tables']['items']['rows'] as $key=>$label) $form['facilities']['items'][$key]=['quantity'=>match($key){'land_acres'=>2.5+$i/2,'library_books'=>400+$i*170,'computers'=>6+$i*3,'desk_capacity'=>$learners+20,'permanent_classrooms'=>8+$i,default=>2+$i%4},'remarks'=>'Synthetic demo quantity; not an assessment of a real school.'];
                    $form['studies']['programmes']=[['name'=>$teaching[0]['name'],'course'=>'Education leadership','institution'=>'Demo Training College']];
                    $form['academic']['ple']=['division_1'=>['candidates'=>12+$i],'division_2'=>['candidates'=>20+$i],'division_3'=>['candidates'=>5],'division_4'=>['candidates'=>2],'division_u'=>['candidates'=>1],'division_x'=>['candidates'=>0]];
                    $form['declaration']=['headteacher_name'=>'Demo Headteacher '.$i,'priest_name'=>'Demo Parish Coordinator','headteacher_signature'=>'DEMO ONLY - not signed','priest_signature'=>'DEMO ONLY - not signed'];
                    $status=$year<2026?'locked':['locked','locked','locked','locked','draft','returned_for_correction','locked','locked','locked','draft'][$i];
                    $return=SchoolReturn::create(['school_id'=>$school->id,'reporting_period_id'=>$period->id,'status'=>$status,'statistical_form'=>$form,'started_by'=>$user->id,'submitted_at'=>in_array($status,['submitted','certified','approved','locked','returned_for_correction']) ? now() : null,'submitted_by'=>in_array($status,['submitted','certified','approved','locked','returned_for_correction']) ? $user->id : null,'locked_at'=>$status==='locked'?now():null,'correction_reason'=>$status==='returned_for_correction'?'Demo: verify enrolment counts':null]);
                    app(StatisticalFormSummaryService::class)->sync($return,'enrolment',$form['enrolment']);
                    app(StatisticalFormSummaryService::class)->sync($return,'teaching',$form['teaching']);
                }
            }
        });
    }
}
