<?php
namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\{ReportingPeriod, School, SchoolReturn};
use App\Services\StatisticalFormQualityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        if ($request->user()->isSchoolRepresentative()) return redirect()->route('representative.home');
        $request->validate(['parish'=>['nullable','integer'], 'level'=>['nullable','string','max:80'], 'district'=>['nullable','integer'], 'year'=>['nullable','integer','between:2000,2100'], 'metric'=>['nullable','in:schools,learners,teaching,support,parishes,computers']]);
        $schools = School::visibleTo($request->user())->orderBy('official_name')->get();
        $filterSchools = $schools;
        $schools = $schools->filter(fn($s)=>(!$request->filled('parish') || $s->parish_id==$request->integer('parish')) && (!$request->filled('level') || $s->school_level===$request->input('level')) && (!$request->filled('district') || $s->district_id==$request->integer('district')));
        $ids = $schools->pluck('id');
        $years = ReportingPeriod::whereHas('schools', fn($q)=>$q->whereIn('schools.id',$ids))->distinct()->orderByDesc('year')->pluck('year')->map(fn($y)=>(int)$y);
        $year = (int)($request->input('year') ?? $years->first() ?? now()->year);
        $years = $years->push($year)->unique()->sortDesc()->values();
        $periods = ReportingPeriod::where('year',$year)->whereHas('schools',fn($q)=>$q->whereIn('schools.id',$ids))->with('schools')->get();
        $assigned = $periods->flatMap(fn($p)=>$p->schools->pluck('id'))->unique()->intersect($ids);
        $expected = $assigned->isNotEmpty() ? $schools->whereIn('id',$assigned) : $schools;
        $returns = SchoolReturn::whereIn('school_id',$expected->pluck('id'))->whereHas('reportingPeriod',fn($q)=>$q->where('year',$year))->with(['school.parish','reportingPeriod'])->latest('updated_at')->orderByDesc('id')->get()->unique('school_id')->values();
        $captured = $returns->filter(fn($r)=>is_array($r->statistical_form) && count($r->statistical_form)>0);
        $forms = $captured->pluck('statistical_form');
        $sumSex = fn($sex)=>$forms->sum(fn($f)=>collect($f['enrolment']['classes']??[])->merge($f['enrolment']['nursery']??[])->sum(fn($r)=>(int)($r[$sex]??0)));
        $boys=$sumSex('boys'); $girls=$sumSex('girls');
        $rowMetric = static function ($form, $metric): int {
            return match($metric) {
                'learners'=>collect($form['enrolment']['classes']??[])->merge($form['enrolment']['nursery']??[])->sum(fn($r)=>(int)($r['boys']??0)+(int)($r['girls']??0)),
                'teaching'=>count($form['teaching']['members']??[]),
                'support'=>count($form['support']['members']??[]),
                'books'=>(int)($form['facilities']['items']['library_books']['quantity']??0),
                'computers'=>(int)($form['facilities']['items']['computers']['quantity']??0),
                default=>1,
            };
        };
        $definitions=['schools'=>['Schools','school'],'learners'=>['Learners','users'],'teaching'=>['Teaching staff','staff'],'support'=>['Support staff','users'],'parishes'=>['Parishes','hierarchy'],'computers'=>['Computers','building']];
        $stats=[];
        foreach($definitions as $key=>[$label,$icon]) {
            if ($key === 'parishes') {
                $stats[]=['key'=>$key,'label'=>$label,'icon'=>$icon,'value'=>$expected->pluck('parish_id')->filter()->unique()->count(),'reported'=>0];
                continue;
            }
            $stats[]=['key'=>$key,'label'=>$label,'icon'=>$icon,'value'=>$key==='schools'?$expected->count():$captured->sum(fn($r)=>$rowMetric($r->statistical_form,$key)),'reported'=>$key==='schools'?$expected->count():$captured->filter(fn($r)=>match($key){'learners'=>isset($r->statistical_form['enrolment']),'teaching'=>isset($r->statistical_form['teaching']),'support'=>isset($r->statistical_form['support']),default=>isset($r->statistical_form['facilities'])})->count()];
        }
        $attention=collect();
        foreach($expected as $school) {
            $return=$returns->firstWhere('school_id',$school->id);
            if(!$return) {
                $period=$periods->first(fn($p)=>$p->schools->contains('id',$school->id));
                $attention->push(['school'=>$school->official_name,'message'=>'No return started','url'=>$period?route('reporting-periods.show',$period):route('reporting-periods.index'),'action'=>'Open reporting period']);
            } elseif(in_array($return->status,['draft','returned_for_correction','not_started'])) {
                $errors=app(StatisticalFormQualityService::class)->errors($return);
                $attention->push(['school'=>$school->official_name,'message'=>$return->status==='returned_for_correction'?'Returned for correction':(count($errors)?count($errors).' checks need attention':'Ready to review and submit'),'url'=>route('returns.form.show',$return),'action'=>'Review form']);
            }
        }
        $metric=$request->input('metric');
        $breakdown=$metric ? ($metric==='schools'?$expected->map(fn($s)=>['school'=>$s->official_name,'value'=>null,'url'=>route('schools.show',$s)]):$captured->map(fn($r)=>['school'=>$r->school->official_name,'value'=>isset($r->statistical_form[match($metric){'learners'=>'enrolment','teaching'=>'teaching','support'=>'support',default=>'facilities'}])?$rowMetric($r->statistical_form,$metric):null,'url'=>route('returns.form.show',[$r,match($metric){'learners'=>'enrolment','teaching'=>'teaching','support'=>'support',default=>'facilities'}])])) : collect();
        if ($metric === 'parishes') {
            $breakdown=$expected->load('parish')->groupBy('parish_id')->filter(fn($group)=>$group->first()->parish_id)->map(fn($group)=>['school'=>$group->first()->parish?->name ?? 'Not recorded','value'=>$group->count(),'url'=>route('schools.index',['parish_id'=>$group->first()->parish_id])])->values();
        }
        $parishLearners=collect(); $parishProgress=collect(); $classes=collect(); $religions=collect(); $qualifications=collect(); $staffLevels=collect();
        foreach($captured as $r) {
            $f=$r->statistical_form; $parish=$r->school->parish?->name ?? 'Unassigned';
            $parishLearners[$parish]=($parishLearners[$parish]??0)+$rowMetric($f,'learners');
            foreach(array_merge($f['enrolment']['classes']??[], $f['enrolment']['nursery']??[]) as $label=>$row) $classes[$label]=($classes[$label]??0)+(int)($row['boys']??0)+(int)($row['girls']??0);
            foreach($f['religion']['community']['learners']??[] as $label=>$value) $religions[$label]=($religions[$label]??0)+(int)$value;
            foreach($f['teaching']['members']??[] as $member) { $label=$member['qualification']??'Not recorded'; $qualifications[$label]=($qualifications[$label]??0)+1; }
            $label=ucfirst(str_replace('_',' ',$r->school->school_level)); $staffLevels[$label]=($staffLevels[$label]??0)+$rowMetric($f,'teaching')+$rowMetric($f,'support');
        }
        foreach($expected->groupBy('parish_id') as $group) { $name=$group->first()->parish?->name??'Unassigned'; $parishProgress[$name]=round($returns->whereIn('school_id',$group->pluck('id'))->whereIn('status',['submitted','certified','approved','locked'])->count()/max(1,$group->count())*100); }
        $trend=collect();
        foreach($years->sort() as $trendYear) {
            $historical=SchoolReturn::whereIn('school_id',$ids)->whereHas('reportingPeriod',fn($q)=>$q->where('year',$trendYear))->whereNotNull('statistical_form')->latest('updated_at')->orderByDesc('id')->get()->unique('school_id');
            if($historical->isNotEmpty()) $trend[$trendYear]=$historical->sum(fn($r)=>$rowMetric($r->statistical_form,'learners'));
        }
        return view('dashboard',compact('year','years','expected','captured','returns','periods','boys','girls','stats','attention','metric','breakdown','definitions','filterSchools','parishLearners','parishProgress','classes','religions','qualifications','staffLevels','trend')+[
            'demoSchoolCount'=>$expected->filter(fn($s)=>str_starts_with($s->diocesan_code,'DEMO-S'))->count(),
            'title'=>'Dashboard',
            'coverage'=>$expected->count()?round($captured->count()/$expected->count()*100):0,
            'coverageBasis'=>$assigned->isNotEmpty()?'Schools assigned to reporting periods in this year':'Visible school registry; no school assignments for this year',
            'schoolLevels'=>$expected->countBy('school_level'),
            'statuses'=>$returns->countBy('status'),
            'approved'=>$returns->whereIn('status',['approved','locked'])->count(),
            'utilities'=>[
                'Reliable water'=>$forms->filter(fn($f)=>($f['utilities']['water_reliable']??null)==='Yes')->count(),
                'Borehole'=>$forms->filter(fn($f)=>in_array('Borehole',$f['utilities']['water_sources']??[]))->count(),
                'National grid'=>$forms->filter(fn($f)=>in_array('Hydro-electricity (national grid)',$f['utilities']['power_sources']??[]))->count(),
                'Solar power'=>$forms->filter(fn($f)=>in_array('Solar system',$f['utilities']['power_sources']??[]))->count(),
            ],
            'facilities'=>[
                'Permanent classrooms'=>$forms->sum(fn($f)=>(int)($f['facilities']['items']['permanent_classrooms']['quantity']??0)),
                'Desk seating capacity'=>$forms->sum(fn($f)=>(int)($f['facilities']['items']['desk_capacity']['quantity']??0)),
                'Library books'=>$forms->sum(fn($f)=>(int)($f['facilities']['items']['library_books']['quantity']??0)),
                'Computers'=>$forms->sum(fn($f)=>(int)($f['facilities']['items']['computers']['quantity']??0)),
            ],
        ]);
    }
}
