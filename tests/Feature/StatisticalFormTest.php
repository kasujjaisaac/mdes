<?php
namespace Tests\Feature;

use App\Models\{Diocese, District, Parish, ReportingPeriod, Role, School, SchoolReturn, User};
use App\Support\StatisticalForm;
use Database\Seeders\{IdentityAccessSeeder, DiocesanStructureSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticalFormTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $school = School::create(['diocese_id'=>Diocese::first()->id, 'parish_id'=>Parish::first()->id, 'district_id'=>District::first()->id, 'official_name'=>'Form School', 'diocesan_code'=>'FORM-01', 'school_level'=>'primary', 'ownership'=>'diocesan', 'operational_status'=>'active']);
        $period = ReportingPeriod::create(['title'=>'2026 census','type'=>'annual_census','year'=>2026,'status'=>'open','opening_date'=>'2026-01-01','closing_date'=>'2026-12-31','created_by'=>$user->id]);
        return [$user, SchoolReturn::create(['school_id'=>$school->id,'reporting_period_id'=>$period->id,'status'=>'draft','started_by'=>$user->id])];
    }

    public function test_dashboard_filters_year_deduplicates_schools_and_links_breakdowns(): void
    {
        [$user,$return] = $this->context();
        $return->reportingPeriod->schools()->sync([$return->school_id]);
        $return->forceFill(['status'=>'locked','statistical_form'=>['enrolment'=>['classes'=>['year_1'=>['boys'=>12,'girls'=>8]]],'teaching'=>['members'=>[['name'=>'One']]]]])->save();
        $other = $return->reportingPeriod->replicate();
        $other->title = '2025 census'; $other->year = 2025; $other->save();
        $other->schools()->attach($return->school_id);
        SchoolReturn::create(['school_id'=>$return->school_id,'reporting_period_id'=>$other->id,'status'=>'locked','statistical_form'=>['enrolment'=>['classes'=>['year_1'=>['boys'=>900,'girls'=>900]]]]]);
        $response=$this->actingAs($user)->get(route('dashboard',['year'=>2026,'basis'=>'provisional']));
        $response->assertOk()->assertDontSee('<footer class="hr-insights">', false)->assertDontSee('Schools needing attention')->assertDontSee('Learners and staffing by school level')->assertDontSee('Comparable enrolment change')->assertDontSee('Academic outcomes')->assertViewHas('boys',12)->assertViewHas('girls',8);
        $this->assertSame(20,$response->viewData('summary')['learners']['value']);
        $this->assertCount(1, $response->viewData('records'));
        $this->get(route('dashboard',['year'=>2025,'basis'=>'provisional']))->assertOk()->assertViewHas('boys',900);
        $empty=$this->get(route('dashboard',['year'=>2024]));
        $empty->assertOk()->assertViewHas('coverage',null)->assertSee('Reporting scope not configured');
        $this->assertNull($empty->viewData('summary')['learners']['value']);
        $this->get(route('dashboard',['year'=>2026,'parish'=>999999]))->assertOk()->assertViewHas('boys',null)->assertViewHas('girls',null);
        $this->get(route('dashboard',['metric'=>'finance']))->assertSessionHasErrors('metric');
    }

    public function test_completeness_counts_assigned_schools_even_without_returns(): void
    {
        [$user, $return] = $this->context();
        $second = $return->school->replicate();
        $second->diocesan_code = 'EXPECTED-02';
        $second->official_name = 'Not started school';
        $second->save();
        $unassigned = $return->school->replicate();
        $unassigned->diocesan_code = 'UNASSIGNED-03';
        $unassigned->save();
        $return->reportingPeriod->schools()->sync([$return->school_id, $second->id]);
        $return->update(['statistical_form'=>['enrolment'=>['classes'=>['year_1'=>['boys'=>12,'girls'=>8]]]]]);
        $response = $this->actingAs($user)->get(route('dashboard', ['year'=>2026]));
        $response->assertOk()->assertViewHas('coverage', 0.0)->assertViewHas('submittedCount', 0)->assertViewHas('outstandingCount', 2);
        $this->assertCount(2, $response->viewData('expected'));
        $return->update(['status'=>'approved']);
        $this->get(route('dashboard', ['year'=>2026]))->assertOk()->assertViewHas('coverage', 50.0)->assertViewHas('submittedCount', 1)->assertViewHas('outstandingCount', 1);
    }

    public function test_unassigned_return_does_not_create_a_reporting_scope(): void
    {
        [$user, $return] = $this->context();
        $return->update(['status'=>'submitted']);
        $response = $this->actingAs($user)->get(route('dashboard', ['year'=>2026]));
        $response->assertOk()->assertViewHas('coverage', null)->assertViewHas('submittedCount', 0)->assertSee('Reporting scope not configured')->assertDontSee('reporting completeness: 0%');
        $this->assertCount(0, $response->viewData('expected'));
    }

    public function test_demo_seed_is_repeatable_and_leaves_existing_records_unchanged(): void
    {
        [$user,$return]=$this->context();
        $name=$return->school->official_name;
        $this->seed(\Database\Seeders\DashboardDemoSeeder::class);
        $this->assertSame(12,School::where('diocesan_code','like','DEMO-S%')->count());
        $this->assertSame(34,SchoolReturn::whereHas('school',fn($q)=>$q->where('diocesan_code','like','DEMO-S%'))->count());
        $this->seed(\Database\Seeders\DashboardDemoSeeder::class);
        $this->assertSame(12,School::where('diocesan_code','like','DEMO-S%')->count());
        $this->assertSame($name,$return->school->fresh()->official_name);
        $this->assertNull($return->fresh()->statistical_form);
        $this->actingAs($user)->get(route('dashboard',['year'=>2026]))->assertOk()->assertDontSee('School Analytics Dashboard')->assertDontSee('<aside class="demo-data-notice">', false);
    }

    public function test_registered_school_profile_does_not_display_statistical_return_data(): void
    {
        [$user,$return]=$this->context();
        $return->forceFill(['statistical_form'=>[
            'identity'=>['school_name'=>'Submitted identity','emis_code'=>'PROFILE-EMIS'],
            'enrolment'=>['classes'=>['year_1'=>['boys'=>11,'girls'=>13]]],
            'teaching'=>['members'=>[['name'=>'Profile Teacher','qualification'=>'Diploma']]],
            'facilities'=>['items'=>['computers'=>['quantity'=>5,'remarks'=>'Profile facility remarks']]],
        ]])->save();
        $this->actingAs($user)->get(route('schools.show',[$return->school,'return_id'=>$return->id]))->assertOk()->assertSee('Registered school profile')->assertSee('Form School')->assertDontSee('Submitted identity')->assertDontSee('PROFILE-EMIS')->assertDontSee('Profile Teacher')->assertDontSee('Profile facility remarks')->assertDontSee('Reporting period');
        $this->get(route('schools.show',[$return->school,'return_id'=>999999]))->assertOk()->assertSee('Form School');
        $this->get(route('schools.show',$return->school))->assertOk()->assertSee('School identity')->assertSee('Contact information');
    }

    public function test_submission_register_renders_and_filters_with_access_scope(): void
    {
        [$user,$return]=$this->context();
        $this->actingAs($user)->get(route('returns.index'))->assertOk()->assertSee('No submissions found')->assertViewHas('returns',fn($rows)=>$rows->total()===0);
        $this->get(route('returns.index',['status'=>'locked']))->assertOk()->assertSee('No submissions found');
        $this->get(route('returns.index',['period_id'=>$return->reporting_period_id,'search'=>'Form School']))->assertOk()->assertViewHas('returns',fn($rows)=>$rows->total()===0);
    }

    private function representativeFor($school): User
    {
        $this->seed(\Database\Seeders\SchoolRepresentativeRoleSeeder::class);
        $user=User::factory()->create(['school_id'=>$school->id]);
        $user->roles()->attach(Role::where('slug','headteacher')->firstOrFail());
        return $user;
    }

    public function test_representative_cannot_access_another_school_even_in_the_same_parish(): void
    {
        [$admin,$return]=$this->context();
        $rep=$this->representativeFor($return->school);
        $other=$return->school->replicate();$other->official_name='Other private school';$other->diocesan_code='OTHER-SCHOOL';$other->save();
        $otherReturn=SchoolReturn::create(['school_id'=>$other->id,'reporting_period_id'=>$return->reporting_period_id,'status'=>'draft','statistical_form'=>['identity'=>['headteacher_name'=>'Private headteacher']]]);
        $return->reportingPeriod->schools()->sync([$return->school_id,$other->id]);
        $this->actingAs($rep)->get(route('dashboard'))->assertRedirect(route('representative.home'));
        $this->get(route('representative.home'))->assertOk()->assertSee('Form School')->assertDontSee('Other private school');
        $this->get(route('schools.index'))->assertRedirect(route('representative.profile'));
        $this->get(route('schools.show',$other))->assertForbidden();
        $this->get(route('returns.show',$otherReturn))->assertForbidden();
        $this->get(route('returns.form.show',[$otherReturn,'identity']))->assertForbidden();
        $this->post(route('returns.form.save',[$otherReturn,'identity']),['data'=>['headteacher_name'=>'Changed']])->assertForbidden();
        $this->get(route('representative.confirm',$otherReturn))->assertForbidden();
        $this->post(route('returns.start',[$return->reportingPeriod,$other]))->assertForbidden();
        $this->get(route('reporting-periods.show',$return->reportingPeriod))->assertOk()->assertDontSee('Other private school');
        $this->get(route('administration.users.index'))->assertForbidden();
    }

    public function test_legacy_notification_page_does_not_expose_another_users_message(): void
    {
        [$admin,$return]=$this->context();$rep=$this->representativeFor($return->school);
        \App\Models\NotificationRecord::create(['user_id'=>$rep->id,'type'=>'school_feedback','message'=>'School-private message','status'=>'open']);
        \App\Models\NotificationRecord::create(['user_id'=>$admin->id,'type'=>'support_request','message'=>'Administrator own message','status'=>'open']);
        $this->actingAs($admin)->get(route('notifications.index'))->assertOk()->assertSee('Administrator own message')->assertDontSee('School-private message');
    }

    public function test_administrator_can_close_and_extend_the_existing_annual_window(): void
    {
        [$admin,$return]=$this->context();$period=$return->reportingPeriod;
        $payload=['title'=>$period->title,'opening_date'=>'2026-01-01','closing_date'=>'2026-12-01','extension_date'=>null,'status'=>'closed'];
        $this->actingAs($admin)->get(route('reporting-periods.edit',$period))->assertOk();
        $this->put(route('reporting-periods.update',$period),$payload)->assertSessionHasNoErrors()->assertRedirect(route('reporting-periods.show',$period));
        $this->assertFalse($period->fresh()->isSubmissionOpen());
        $this->put(route('reporting-periods.update',$period),[...$payload,'status'=>'open','extension_date'=>'2026-12-15','year'=>2027])->assertSessionHasNoErrors();
        $this->assertTrue($period->fresh()->isSubmissionOpen());
        $this->assertSame(2026,$period->fresh()->year);
        $rep=$this->representativeFor($return->school);
        $this->actingAs($rep)->put(route('reporting-periods.update',$period),$payload)->assertForbidden();
    }

    public function test_repeated_permission_checks_do_not_repeat_database_queries(): void
    {
        [$admin,$return]=$this->context();$rep=$this->representativeFor($return->school);
        $rep->hasPermission('returns.view');
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        for($i=0;$i<10;$i++){$rep->hasPermission('returns.view');$rep->isSchoolRepresentative();}
        $this->assertCount(0,\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
    }

    public function test_representative_can_correct_own_profile_without_changing_submitted_data(): void
    {
        [$admin,$return]=$this->context();$rep=$this->representativeFor($return->school);
        $return->update(['status'=>'submitted','submitted_at'=>now(),'statistical_form'=>['identity'=>['emis_code'=>'ORIGINAL']]]);
        $school=$return->school;
        $this->actingAs($rep)->get(route('representative.profile.edit'))->assertOk()->assertSee('Save changes');
        $this->put(route('representative.profile.update'),['parish_id'=>$school->parish_id,'district_id'=>$school->district_id,'school_level'=>'primary','funding_classification'=>'Private','gender_classification'=>'Mixed','boarding_status'=>'Day','ministry_registration_number'=>'MOE-CORRECTED-001','emis_number'=>'CORRECTED-EMIS','official_name'=>'Forged rename','school_id'=>999])->assertSessionHasNoErrors()->assertRedirect(route('representative.profile'));
        $this->assertSame('CORRECTED-EMIS',$school->fresh()->emis_number);
        $this->assertSame('Form School',$school->fresh()->official_name);
        $this->assertSame('ORIGINAL',$return->fresh()->statistical_form['identity']['emis_code']);
        $this->actingAs($admin)->get(route('representative.profile.edit'))->assertForbidden();
    }

    public function test_support_requests_are_private_and_administrator_replies_notify_the_requester(): void
    {
        [$admin,$return]=$this->context();$rep=$this->representativeFor($return->school);
        $this->actingAs($rep)->get(route('support.index'))->assertOk()->assertSee('How can we help?');
        $this->post(route('support.store'),['category'=>'Form entry','description'=>'Cannot save the enrolment section.'])->assertRedirect(route('support.index'));
        $ticket=\App\Models\HelpdeskTicket::where('opened_by',$rep->id)->firstOrFail();
        $this->assertSame($return->school_id,$ticket->school_id);
        $this->post(route('support.reply',$ticket),['message'=>'Forged response','status'=>'resolved'])->assertForbidden();
        $foreign=\App\Models\HelpdeskTicket::create(['opened_by'=>$admin->id,'category'=>'Other','description'=>'Private admin support issue','status'=>'open','priority'=>'normal']);
        $this->get(route('support.index'))->assertDontSee('Private admin support issue');
        $this->actingAs($admin)->get(route('support.index'))->assertOk()->assertSee('Cannot save the enrolment section.');
        $this->post(route('support.reply',$ticket),['message'=>'Please retry saving your section.','status'=>'resolved'])->assertRedirect();
        $this->assertSame('resolved',$ticket->fresh()->status);
        $this->actingAs($rep)->get(route('support.index'))->assertOk()->assertSee('Please retry saving your section.');
        $this->get(route('alerts.index'))->assertOk()->assertSee('Support reply #'.$ticket->id);
    }

    public function test_school_alerts_are_private_and_admin_feedback_can_be_read(): void
    {
        [$admin,$return]=$this->context();
        $rep=$this->representativeFor($return->school);
        $return->update(['status'=>'submitted','submitted_at'=>now()]);
        $this->actingAs($admin)->post(route('alerts.store'),['school_return_id'=>$return->id,'subject'=>'Enrolment feedback','message'=>'Please review the recorded totals.'])->assertRedirect(route('alerts.index'));
        $alert=\App\Models\NotificationRecord::where('user_id',$rep->id)->firstOrFail();
        $this->actingAs($rep)->get(route('alerts.index'))->assertOk()->assertSee('Enrolment feedback')->assertSee('Mark as read');
        $this->post(route('alerts.store'),['school_return_id'=>$return->id,'subject'=>'No','message'=>'No'])->assertForbidden();
        $foreign=\App\Models\NotificationRecord::create(['user_id'=>$admin->id,'type'=>'school_feedback','subject'=>'Private admin message','message'=>'Hidden']);
        $this->get(route('alerts.index'))->assertDontSee('Private admin message');
        $this->post(route('alerts.read',$foreign))->assertForbidden();
        $this->post(route('alerts.read',$alert))->assertRedirect();
        $this->assertNotNull($alert->fresh()->read_at);
        $this->get(route('alerts.index',['filter'=>'unread']))->assertDontSee('Enrolment feedback');
    }

    public function test_submitted_table_renders_missing_sections_and_empty_rosters(): void
    {
        [$admin,$return]=$this->context();
        $rep=$this->representativeFor($return->school);
        $return->update(['status'=>'submitted','submitted_at'=>now(),'statistical_form'=>['identity'=>['school_name'=>'Saved school name'],'teaching'=>['members'=>[]]]]);
        $this->actingAs($rep)->get(route('returns.show',$return))->assertOk()->assertSee('Saved school name')->assertSee('Not recorded')->assertSee('No entries recorded')->assertDontSee('Read-only');
    }

    public function test_admin_cannot_create_two_annual_windows_for_one_year(): void
    {
        [$admin,$return]=$this->context();
        $this->actingAs($admin)->post(route('reporting-periods.store'),['title'=>'Duplicate annual collection','type'=>'annual_census','year'=>$return->reportingPeriod->year,'opening_date'=>'2026-01-01','closing_date'=>'2026-12-01','status'=>'open','school_ids'=>[]])->assertSessionHasErrors('year');
        $this->assertSame(1,\App\Models\ReportingPeriod::where('annual_year',$return->reportingPeriod->year)->count());
    }

    public function test_school_cannot_submit_another_collection_in_the_same_year(): void
    {
        [$admin,$return]=$this->context();
        $otherPeriod=$return->reportingPeriod->replicate();
        $otherPeriod->title='Archived duplicate annual window';
        $otherPeriod->is_archived=true;
        $otherPeriod->save();
        $previous=$return->replicate();
        $previous->reporting_period_id=$otherPeriod->id;
        $previous->status='submitted';
        $previous->submitted_at=now();
        $previous->save();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already submitted its annual form');
        app(\App\Actions\TransitionSchoolReturn::class)->submit($return,$admin);
    }

    public function test_submission_locks_until_admin_returns_it_and_school_resubmits_corrections(): void
    {
        [$admin, $return] = $this->context();
        $rep = $this->representativeFor($return->school);
        $return->reportingPeriod->schools()->sync([$return->school_id]);
        $return->reportingPeriod->update(['opening_date'=>now()->subDay(),'closing_date'=>now()->addDay(),'status'=>'open']);
        $form = array_fill_keys(array_keys(StatisticalForm::sections()), []);
        $form['identity'] = ['date'=>now()->toDateString(),'school_name'=>'Form School','level'=>'Primary','funding'=>'Government','gender'=>'Mixed','boarding'=>'Day','headteacher_name'=>'Headteacher'];
        $form['enrolment'] = ['classes'=>['year_1'=>['boys'=>12,'girls'=>14]]];
        $form['declaration'] = ['headteacher_name'=>'Headteacher','priest_name'=>'Father'];
        $return->forceFill(['statistical_form'=>$form,'profile_confirmed_at'=>now(),'profile_confirmed_by'=>$rep->id])->save();
        $this->actingAs($rep)->post(route('returns.transition',$return),['action'=>'submit','acknowledged'=>1])->assertSessionHasNoErrors();
        $this->assertSame('locked', $return->fresh()->status);
        $this->assertNotNull($return->fresh()->locked_at);
        $this->post(route('returns.form.save',[$return,'enrolment']),['data'=>[]])->assertForbidden();
        $this->post(route('returns.transition',$return),['action'=>'return_for_correction','reason'=>'Change enrolment'])->assertForbidden();
        $this->actingAs($admin)->post(route('returns.form.save',[$return,'enrolment']),['data'=>[]])->assertForbidden();
        $this->get(route('dashboard',['year'=>2026]))->assertOk()->assertViewHas('boys',12)->assertDontSee('Approved / locked')->assertDontSee('Provisional');
        foreach (['certify','lock'] as $action) $this->post(route('returns.transition',$return),['action'=>$action])->assertSessionHasErrors('action');
        $this->post(route('returns.transition',$return),['action'=>'return_for_correction'])->assertSessionHasErrors('reason');
        $this->post(route('returns.transition',$return),['action'=>'return_for_correction','reason'=>'Correct the boys count'])->assertSessionHasNoErrors();
        $this->assertSame('returned_for_correction', $return->fresh()->status);
        $this->assertNull($return->fresh()->locked_at);
        $this->get(route('dashboard',['year'=>2026]))->assertOk()->assertViewHas('boys',null);
        $return->reportingPeriod->update(['status'=>'closed','closing_date'=>now()->subDay()]);
        $this->actingAs($rep)->get(route('representative.home'))->assertOk()->assertSee('Continue corrections');
        $this->post(route('returns.form.save',[$return,'enrolment']),['data'=>['classes'=>['year_1'=>['boys'=>13,'girls'=>14]]]])->assertSessionHasNoErrors();
        $this->post(route('returns.transition',$return),['action'=>'submit','acknowledged'=>1])->assertSessionHasNoErrors();
        $this->assertSame('locked', $return->fresh()->status);
        $this->assertNull($return->fresh()->correction_reason);
        $this->actingAs($admin)->get(route('dashboard',['year'=>2026]))->assertOk()->assertViewHas('boys',13);
    }

    public function test_representative_confirms_profile_saves_drafts_and_submits_to_admin(): void
    {
        [$admin,$return]=$this->context();$rep=$this->representativeFor($return->school);
        $return->reportingPeriod->schools()->sync([$return->school_id]);
        $this->actingAs($rep)->get(route('returns.form.show',$return))->assertRedirect(route('representative.confirm',$return));
        $this->post(route('returns.form.save',[$return,'enrolment']),['data'=>['classes'=>['year_1'=>['boys'=>1,'girls'=>2]]]])->assertRedirect(route('representative.confirm',$return));
        $this->get(route('representative.confirm',$return))->assertOk()->assertSee('I confirm')->assertDontSee('First step: confirm your school profile')->assertDontSee('Source form sections');
        $payload=['ministry_registration_number'=>'MOE-FORM-CONFIRM-001','parish_id'=>$return->school->parish_id,'district_id'=>$return->school->district_id,'school_level'=>'primary','funding_classification'=>'Government','gender_classification'=>'Mixed','boarding_status'=>'Day'];
        $this->post(route('representative.confirm.save',$return),$payload)->assertSessionHasErrors('confirmed');
        $this->post(route('representative.confirm.save',$return),[...$payload,'confirmed'=>1,'official_name'=>'Attempted rename'])->assertSessionHasNoErrors()->assertRedirect(route('returns.form.show',$return));
        $return->refresh();$this->assertSame($rep->id,$return->profile_confirmed_by);$this->assertSame('Form School',$return->school->fresh()->official_name);
        $data=array_fill_keys(array_keys(StatisticalForm::sections()),[]);
        $data['identity']=['date'=>'2026-11-03','headteacher_name'=>'School representative','school_name'=>'Forged school'];
        $data['enrolment']=['classes'=>['year_1'=>['boys'=>12,'girls'=>14]]];
        $data['declaration']=['headteacher_name'=>'Headteacher','priest_name'=>'Father'];
        foreach($data as $section=>$values) $this->post(route('returns.form.save',[$return,$section]),['data'=>$values])->assertSessionHasNoErrors()->assertRedirect(route('returns.form.show',[$return,$section]));
        $this->assertCount(10,$return->fresh()->statistical_form);
        $this->assertSame('Form School',$return->fresh()->statistical_form['identity']['school_name']);
        $this->assertSame('draft',$return->fresh()->status);
        $this->post(route('returns.transition',$return),['action'=>'submit'])->assertSessionHasErrors('acknowledged');
        $this->post(route('returns.transition',$return),['action'=>'submit','acknowledged'=>1])->assertSessionHasNoErrors();
        $this->get(route('returns.show',$return))->assertOk()->assertSee('annual submission')->assertSee('Submitted value');
        $this->post(route('returns.form.save',[$return,'enrolment']),['data'=>[]])->assertForbidden();
        $this->assertSame('locked',$return->fresh()->status);
        $this->post(route('returns.form.save',[$return,'identity']),['data'=>[]])->assertForbidden();
        $this->post(route('returns.transition',$return),['action'=>'certify'])->assertForbidden();
        $this->actingAs($admin)->get(route('returns.index',['status'=>'submitted']))->assertOk()->assertSee('Form School');
        $this->post(route('returns.transition',$return),['action'=>'certify'])->assertSessionHasErrors('action');
        $this->assertSame('locked',$return->fresh()->status);
    }

    public function test_closed_window_blocks_representative_writes_and_submission(): void
    {
        [$admin,$return]=$this->context();$rep=$this->representativeFor($return->school);
        $return->reportingPeriod->update(['status'=>'closed']);
        $this->actingAs($rep)->post(route('returns.form.save',[$return,'identity']),['data'=>[]])->assertForbidden();
        $this->post(route('returns.transition',$return),['action'=>'submit'])->assertForbidden();
        $this->assertSame('draft',$return->fresh()->status);
    }

    public function test_representative_dashboard_shows_window_and_form_states(): void
    {
        [$admin,$return]=$this->context();$rep=$this->representativeFor($return->school);
        $period=$return->reportingPeriod;$period->schools()->sync([$return->school_id]);
        $this->actingAs($rep)->get(route('representative.home'))->assertOk()->assertDontSee('<section class="representative-profile">',false)->assertSee('Window open')->assertSee('Continue draft');
        $return->forceFill(['status'=>'submitted','submitted_at'=>now()])->save();
        $this->get(route('representative.home'))->assertOk()->assertSee('Pending review')->assertSee('View submitted form')->assertDontSee('Continue draft');
        $return->update(['status'=>'draft']);$period->update(['status'=>'closed']);
        $this->get(route('representative.home'))->assertOk()->assertSee('Window closed')->assertSee('View saved form')->assertDontSee('Continue draft');
        $return->delete();$period->update(['status'=>'open']);
        $this->get(route('representative.home'))->assertOk()->assertSee('Start form')->assertSee('Not started');
    }

    public function test_representative_statistics_use_only_the_selected_school_form(): void
    {
        [$admin,$return]=$this->context();$rep=$this->representativeFor($return->school);
        $return->forceFill(['statistical_form'=>['enrolment'=>['classes'=>['year_1'=>['boys'=>8,'girls'=>12]]],'teaching'=>['members'=>[['name'=>'One']]],'facilities'=>['items'=>['computers'=>['quantity'=>4]]]]])->save();
        $response=$this->actingAs($rep)->get(route('representative.home',['stats_return_id'=>$return->id]));
        $response->assertOk()->assertDontSee('Learners by gender')->assertViewHas('schoolBoys',8)->assertViewHas('schoolGirls',12);
        $this->assertSame(20,$response->viewData('schoolStatistics')[0]['value']);
        $this->assertSame(4,$response->viewData('schoolStatistics')[5]['value']);
        $this->get(route('representative.home',['stats_return_id'=>999999]))->assertNotFound();
    }

    public function test_representative_has_a_dedicated_own_school_profile(): void
    {
        [$admin,$return]=$this->context();$rep=$this->representativeFor($return->school);
        $this->actingAs($rep)->get(route('representative.profile'))->assertOk()->assertViewHas('school',fn($school)=>$school->id===$return->school_id)->assertSee('Registration number');
        $this->get(route('representative.home'))->assertOk()->assertDontSee('<section class="representative-profile">',false);
        $this->actingAs($admin)->get(route('representative.profile'))->assertForbidden();
    }

    public function test_retired_modules_and_extra_fields_are_not_available(): void
    {
        [$user,$return] = $this->context();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('inspection visits')->assertDontSee('financial summaries')->assertDontSee('School Operational Status');
        $this->get(route('schools.create'))->assertOk()->assertDontSee('name="latitude"', false)->assertDontSee('name="operational_status"', false)->assertDontSee('name="diocesan_code"', false);
        $this->get(route('returns.show',$return))->assertOk()->assertDontSee('attendance and welfare')->assertDontSee('governance and compliance');
        foreach (['/oversight/inspections','/oversight/projects','/school-statistics/financial-statistics','/communications','/helpdesk','/school-statistics/returns/'.$return->id.'/enrolment'] as $url) $this->get($url)->assertNotFound();
        $this->post('/school-statistics/financial-statistics',['school_id'=>$return->school_id])->assertNotFound();
    }

    public function test_every_source_section_renders_and_preserves_other_sections(): void
    {
        [$user, $return] = $this->context();
        $user=$this->representativeFor($return->school);
        $return->forceFill(['profile_confirmed_at'=>now(),'profile_confirmed_by'=>$user->id])->save();
        foreach (StatisticalForm::sections() as $key => $schema) {
            $this->actingAs($user)->get(route('returns.form.show', [$return,$key]))->assertOk()->assertSee('Save this section');
        }
        $this->post(route('returns.form.save',[$return,'identity']), ['data'=>['school_name'=>'Form School','level'=>'Primary']])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('returns.form.save',[$return,'utilities']), ['data'=>['water_sources'=>['Borehole','Surface tank'],'water_reliable'=>'No','water_explanation'=>'Seasonal']])->assertSessionHasNoErrors();
        $return->refresh();
        $this->assertSame('Form School',$return->statistical_form['identity']['school_name']);
        $this->assertSame(['Borehole','Surface tank'],$return->statistical_form['utilities']['water_sources']);
        $this->get(route('returns.form.show',[$return,'utilities']))->assertSee('Seasonal');
    }

    public function test_enrolment_and_staff_feed_summary_counts_without_duplicate_rows(): void
    {
        [$user,$return] = $this->context();
        $user=$this->representativeFor($return->school);
        $return->forceFill(['profile_confirmed_at'=>now(),'profile_confirmed_by'=>$user->id])->save();
        $payload=['data'=>['classes'=>['year_1'=>['boys'=>12,'girls'=>9]],'nursery'=>['baby'=>['boys'=>3,'girls'=>4]]]];
        $this->actingAs($user)->post(route('returns.form.save',[$return,'enrolment']),$payload)->assertSessionHasNoErrors();
        $this->post(route('returns.form.save',[$return,'enrolment']),$payload)->assertSessionHasNoErrors();
        $this->assertEquals(28,$return->enrolmentStatistics()->sum('total'));
        $this->assertEquals(2,$return->enrolmentStatistics()->count());
        $this->post(route('returns.form.save',[$return,'teaching']),['data'=>['members'=>[4=>['name'=>'Teacher A','gender'=>'Female','age'=>35,'payroll'=>'Government','license_number'=>'T-1']]]])->assertSessionHasNoErrors();
        $this->post(route('returns.form.save',[$return,'support']),['data'=>['members'=>[['name'=>'Staff B','gender'=>'Male','payroll'=>'Private']]]])->assertSessionHasNoErrors();
        $this->assertEquals(2,$return->staffStatistic()->first()->filled_positions);
        $this->assertSame('T-1',$return->fresh()->statistical_form['teaching']['members'][4]['license_number']);
    }

    public function test_source_form_submission_requires_review_and_preserves_the_existing_workflow(): void
    {
        [$user,$return] = $this->context();
        $return->forceFill(['statistical_form'=>['identity'=>[]]])->save();
        try {
            app(\App\Actions\TransitionSchoolReturn::class)->submit($return, $user);
            $this->fail('Incomplete source form was submitted.');
        } catch (\DomainException $error) {
            $this->assertStringContainsString('must be reviewed', $error->getMessage());
        }
        $form = array_fill_keys(array_keys(StatisticalForm::sections()), []);
        $form['identity'] = ['date'=>'2026-11-03','school_name'=>'Form School','level'=>'Primary','funding'=>'Government','gender'=>'Mixed','boarding'=>'Day','headteacher_name'=>'Headteacher'];
        $form['enrolment'] = ['classes'=>['year_1'=>['boys'=>0,'girls'=>0]]];
        $form['declaration'] = ['headteacher_name'=>'Headteacher','priest_name'=>'Father'];
        $return->forceFill(['statistical_form'=>$form])->save();
        app(\App\Actions\TransitionSchoolReturn::class)->submit($return, $user);
        $this->assertSame('locked',$return->fresh()->status);
        $this->assertNotNull($return->fresh()->locked_at);
        $this->assertSame('Form School',$return->fresh()->statistical_form['identity']['school_name']);
        $this->actingAs($user)->post(route('returns.form.save',[$return,'identity']),['data'=>['school_name'=>'Changed']])->assertForbidden();
    }

    public function test_form_schema_contains_all_facilities_and_separate_uce_measures(): void
    {
        $schema = StatisticalForm::sections();
        $this->assertCount(20, $schema['facilities']['tables']['items']['rows']);
        $this->assertCount(7, $schema['enrolment']['tables']['classes']['rows']);
        $this->assertCount(3, $schema['enrolment']['tables']['nursery']['rows']);
        $this->assertCount(5, $schema['academic']['tables']['uce_scores']['rows']);
        $this->assertCount(4, $schema['academic']['tables']['uce_results']['rows']);
        $this->assertArrayHasKey('license_number', $schema['teaching']['tables']['members']['columns']);
        $this->assertArrayNotHasKey('license_number', $schema['support']['tables']['members']['columns']);
    }

    public function test_negative_counts_invalid_choices_and_fractional_facility_counts_are_rejected(): void
    {
        [$user,$return] = $this->context();
        $user=$this->representativeFor($return->school);
        $return->forceFill(['profile_confirmed_at'=>now(),'profile_confirmed_by'=>$user->id])->save();
        $this->actingAs($user)->post(route('returns.form.save',[$return,'enrolment']),['data'=>['classes'=>['year_1'=>['boys'=>-1]]]])->assertSessionHasErrors('data.classes.year_1.boys');
        $this->post(route('returns.form.save',[$return,'identity']),['data'=>['level'=>'Invalid']])->assertSessionHasErrors('data.level');
        $this->post(route('returns.form.save',[$return,'facilities']),['data'=>['items'=>['computers'=>['quantity'=>1.5]]]])->assertSessionHasErrors('data.items.computers.quantity');
        $this->post(route('returns.form.save',[$return,'facilities']),['data'=>['items'=>['land_acres'=>['quantity'=>1.5,'remarks'=>'Surveyed']]]])->assertSessionHasNoErrors();
    }

    public function test_locked_returns_are_read_only_and_unassigned_users_cannot_read_staff(): void
    {
        [$user,$return] = $this->context();
        $return->update(['status'=>'locked']);
        $this->actingAs($user)->get(route('returns.form.show',[$return,'teaching']))->assertOk()->assertDontSee('Save this section');
        $this->post(route('returns.form.save',[$return,'teaching']),['data'=>[]])->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('returns.form.show',[$return,'teaching']))->assertForbidden();
    }

    public function test_mobile_navigation_saves_before_moving_and_preserves_validation(): void
    {
        [$admin, $return] = $this->context();
        $rep = $this->representativeFor($return->school);
        $return->forceFill(['profile_confirmed_at'=>now(), 'profile_confirmed_by'=>$rep->id, 'profile_snapshot'=>[]])->save();
        $keys = array_keys(StatisticalForm::sections());
        $index = array_search('enrolment', $keys, true);
        $this->actingAs($rep)->post(route('returns.form.save', [$return,'enrolment']), [
            'navigate'=>'next', 'data'=>['classes'=>['year_1'=>['boys'=>3,'girls'=>4]]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('returns.form.show', [$return,$keys[$index+1]]));
        $this->assertSame(3, $return->fresh()->statistical_form['enrolment']['classes']['year_1']['boys']);
        $this->post(route('returns.form.save', [$return,'enrolment']), [
            'navigate'=>'previous', 'data'=>['classes'=>['year_1'=>['boys'=>5,'girls'=>6]]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('returns.form.show', [$return,$keys[$index-1]]));
        $this->assertSame(5, $return->fresh()->statistical_form['enrolment']['classes']['year_1']['boys']);
        $this->from(route('returns.form.show', [$return,'enrolment']))->post(route('returns.form.save', [$return,'enrolment']), [
            'navigate'=>'next', 'data'=>['classes'=>['year_1'=>['boys'=>-1,'girls'=>6]]],
        ])->assertSessionHasErrors('data.classes.year_1.boys')->assertRedirect(route('returns.form.show', [$return,'enrolment']));
        $this->assertSame(5, $return->fresh()->statistical_form['enrolment']['classes']['year_1']['boys']);
    }

    public function test_saved_sections_are_distinguished_from_submission_readiness(): void
    {
        [$admin, $return] = $this->context();
        $rep = $this->representativeFor($return->school);
        $form = array_fill_keys(array_keys(StatisticalForm::sections()), []);
        $return->forceFill(['profile_confirmed_at'=>now(), 'profile_confirmed_by'=>$rep->id, 'statistical_form'=>$form])->save();
        $this->actingAs($rep)->get(route('returns.form.show', [$return,'enrolment']))
            ->assertOk()->assertSee('100%')->assertSee('Further checks needed before submission')->assertSee('School particulars:');
        $form['identity'] = ['date'=>'2026-11-03','school_name'=>'Form School','level'=>'Primary','funding'=>'Private','gender'=>'Mixed','boarding'=>'Day','headteacher_name'=>'Teacher'];
        $form['enrolment'] = ['classes'=>['year_1'=>['boys'=>0,'girls'=>0]]];
        $form['declaration'] = ['headteacher_name'=>'Teacher','priest_name'=>'Priest'];
        $return->forceFill(['statistical_form'=>$form])->save();
        $this->get(route('returns.form.show', [$return,'declaration']))->assertOk()->assertSee('Ready for final review');
    }
}
