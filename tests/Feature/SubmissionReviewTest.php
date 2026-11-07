<?php

namespace Tests\Feature;

use App\Models\Diocese;
use App\Models\District;
use App\Models\NotificationRecord;
use App\Models\Parish;
use App\Models\Permission;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\SubmissionVersion;
use App\Models\User;
use App\Support\StatisticalForm;
use Database\Seeders\DiocesanStructureSeeder;
use Database\Seeders\IdentityAccessSeeder;
use Database\Seeders\SchoolRepresentativeRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionReviewTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed([IdentityAccessSeeder::class, DiocesanStructureSeeder::class, SchoolRepresentativeRoleSeeder::class]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail());
        $school = School::create(['diocese_id' => Diocese::first()->id, 'parish_id' => Parish::first()->id, 'district_id' => District::first()->id, 'official_name' => 'Review school', 'diocesan_code' => 'REVIEW-1', 'school_level' => 'primary', 'ownership' => 'diocesan', 'operational_status' => 'active']);
        $rep = User::factory()->create(['school_id' => $school->id, 'status' => 'active']);
        $rep->roles()->attach(Role::where('slug', 'headteacher')->firstOrFail());
        $period = ReportingPeriod::create(['title' => 'Review window', 'type' => 'annual', 'year' => 2026, 'status' => 'open', 'opening_date' => now()->subDay(), 'closing_date' => now()->addDay(), 'is_archived' => false, 'created_by' => $admin->id]);
        $return = SchoolReturn::create(['school_id' => $school->id, 'reporting_period_id' => $period->id, 'status' => 'locked', 'submitted_at' => now(), 'submitted_by' => $rep->id, 'locked_at' => now(), 'statistical_form' => ['enrolment' => ['classes' => ['year_1' => ['boys' => 10, 'girls' => 12]]]]]);

        return [$admin, $rep, $return];
    }

    public function test_admin_reviews_and_approves_without_editing_school_answers(): void
    {
        [$admin,$rep,$return] = $this->context();
        $before = $return->statistical_form;
        $this->actingAs($admin)->get(route('returns.index'))->assertOk()->assertSee('Pending review')->assertSee('Review school')->assertDontSee('value="approve"', false)->assertDontSee('value="return_for_correction"', false);
        $fullPage = $this->get(route('returns.show', $return))->assertOk()->assertViewIs('returns.submitted')->assertSee('Approve submission')->assertSee('data-open-correction-dialog', false)->assertSee('data-correction-dialog', false)->assertSee('>Send</button>', false)->assertSee('>10</td>', false);
        $this->assertLessThan(strpos($fullPage->getContent(), 'id="submission-review-title"'), strpos($fullPage->getContent(), 'id="submitted-declaration"'));
        $this->get(route('returns.form.show', [$return, 'enrolment']))->assertOk()->assertViewHas('editable', false);
        $this->post(route('returns.form.save', [$return, 'enrolment']), ['data' => []])->assertForbidden();
        $this->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'approve'])->assertSessionHasNoErrors();
        $return->refresh();
        $this->assertSame('approved', $return->status);
        $this->assertSame($admin->id, $return->approved_by);
        $this->assertNotNull($return->approved_at);
        $this->assertSame($before, $return->statistical_form);
        $this->assertNotNull($return->locked_at);
        $this->assertDatabaseHas('audit_events', ['action' => 'submission.approve', 'auditable_id' => $return->id]);
        $this->assertDatabaseHas('notification_records', ['user_id' => $rep->id, 'subject' => 'Submission approved']);
        $this->get(route('returns.show', $return))->assertOk()->assertSee('Review history')->assertDontSee('Approve submission')->assertDontSee('class="button review-return-trigger"', false);
        $this->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'approve'])->assertSessionHasErrors('return');
        $this->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'return_for_correction', 'reason' => 'No'])->assertSessionHasErrors('return');
        $this->actingAs($rep)->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'approve'])->assertForbidden();
        $this->post(route('returns.form.save', [$return, 'enrolment']), ['data' => []])->assertForbidden();
    }

    public function test_return_requires_reason_notifies_school_and_admin_cannot_edit_returned_form(): void
    {
        [$admin,$rep,$return] = $this->context();
        $this->actingAs($admin)->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'return_for_correction'])->assertSessionHasErrors('reason');
        $this->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'return_for_correction', 'reason' => 'Please verify the learner count.'])->assertSessionHasNoErrors();
        $this->assertSame('returned_for_correction', $return->fresh()->status);
        $this->assertNull($return->fresh()->locked_at);
        $this->assertDatabaseHas('notification_records', ['user_id' => $rep->id, 'message' => 'Please verify the learner count.']);
        $this->post(route('returns.form.save', [$return, 'enrolment']), ['data' => []])->assertForbidden();
        $this->get(route('returns.form.show', [$return, 'enrolment']))->assertViewHas('editable', false);
        $this->post(route('returns.transition', $return), ['action' => 'submit'])->assertForbidden();
        $this->get(route('returns.show', $return))->assertOk()->assertSee('Please verify the learner count.');
    }

    public function test_queue_excludes_drafts_and_filters_approved_separately(): void
    {
        [$admin,$rep,$return] = $this->context();
        $other = $return->school->replicate();
        $other->official_name = 'Draft only school';
        $other->diocesan_code = 'DRAFT-REVIEW';
        $other->save();
        SchoolReturn::create(['school_id' => $other->id, 'reporting_period_id' => $return->reporting_period_id, 'status' => 'draft']);
        $this->actingAs($admin)->get(route('returns.index'))->assertOk()->assertDontSee('Draft only school')->assertViewHas('returns', fn ($rows) => $rows->total() === 1);
        $this->get(route('returns.index', ['status' => 'approved']))->assertViewHas('returns', fn ($rows) => $rows->total() === 0);
        $return->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $admin->id]);
        $this->get(route('returns.index', ['status' => 'approved']))->assertViewHas('returns', fn ($rows) => $rows->total() === 1);
        $this->get(route('returns.index', ['status' => 'pending_review']))->assertViewHas('returns', fn ($rows) => $rows->total() === 0);
        $this->get(route('returns.index', ['year' => 2025]))->assertViewHas('returns', fn ($rows) => $rows->total() === 0);
        $this->get(route('returns.index', ['parish_id' => $return->school->parish_id, 'level' => 'primary']))->assertViewHas('returns', fn ($rows) => $rows->total() === 1);
    }

    public function test_archived_forms_cannot_be_approved_or_returned(): void
    {
        [$admin,$rep,$return] = $this->context();
        $return->reportingPeriod->update(['is_archived' => true]);
        $this->actingAs($admin)->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'approve'])->assertSessionHasErrors('return');
        $this->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'return_for_correction', 'reason' => 'Check'])->assertSessionHasErrors('return');
        $this->assertSame('locked', $return->fresh()->status);
    }

    public function test_reviewer_scope_is_enforced_for_queue_and_review_actions(): void
    {
        [$admin, $rep, $return] = $this->context();
        $role = Role::create(['slug' => 'scoped-reviewer', 'name' => 'Scoped reviewer']);
        $role->permissions()->sync(Permission::whereIn('slug', ['returns.view', 'returns.review', 'schools.view.assigned'])->pluck('id'));
        $reviewer = User::factory()->create();
        $reviewer->roles()->attach($role);
        $this->actingAs($reviewer)->get(route('returns.index'))->assertForbidden();
        $this->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'approve'])->assertForbidden();
        $this->post(route('returns.transition', $return), ['review_token' => StatisticalForm::adminReviewFingerprint($return->fresh()), 'action' => 'return_for_correction', 'reason' => 'No scope'])->assertForbidden();
        $this->get(route('returns.show', $return))->assertForbidden();
        $this->assertSame('locked', $return->fresh()->status);
    }

    public function test_approval_migration_preserves_recorded_approval_and_school_data(): void
    {
        [$admin, $rep, $return] = $this->context();
        $return->update(['approved_by' => $admin->id, 'approved_at' => now()]);
        $before = $return->fresh();
        $migration = require database_path('migrations/2026_11_06_000004_restore_submission_approval_status.php');
        $migration->up();
        $return->refresh();
        $this->assertSame('approved', $return->status);
        $this->assertSame($before->statistical_form, $return->statistical_form);
        $this->assertEquals($before->approved_at, $return->approved_at);
        $this->assertEquals($before->updated_at, $return->updated_at);
    }

    public function test_submission_correction_resubmission_and_approval_are_connected_and_versioned(): void
    {
        [$admin,$rep,$return] = $this->context();
        $return->reportingPeriod->schools()->sync([$return->school_id]);
        $form = array_fill_keys(array_diff(array_keys(StatisticalForm::sections()), ['declaration']), []);
        $form['identity'] = ['date' => now()->toDateString(), 'school_name' => 'Review school', 'level' => 'Primary', 'funding' => 'Private', 'gender' => 'Mixed', 'boarding' => 'Day', 'headteacher_name' => 'Headteacher'];
        $form['enrolment'] = ['classes' => ['year_1' => ['boys' => 10, 'girls' => 12]]];
        $return->forceFill(['status' => 'draft', 'submitted_at' => null, 'submitted_by' => null, 'locked_at' => null, 'profile_confirmed_at' => now(), 'statistical_form' => $form])->save();
        $submit = fn () => ['action' => 'submit', 'acknowledged' => 1, 'review_fingerprint' => StatisticalForm::reviewFingerprint($return->fresh())];
        $this->actingAs($rep)->post(route('returns.transition', $return), $submit())->assertSessionHasNoErrors();
        $this->assertSame('locked', $return->fresh()->status);
        $this->assertDatabaseHas('notification_records', ['user_id' => $admin->id, 'type' => 'submission_received', 'school_return_id' => $return->id]);
        $this->assertDatabaseHas('notification_records', ['user_id' => $rep->id, 'type' => 'submission_receipt', 'school_return_id' => $return->id]);
        $first = SubmissionVersion::where('school_return_id', $return->id)->firstOrFail();
        $hash = $first->content_hash;
        $oldToken = $this->actingAs($admin)->get(route('returns.show', $return))->assertOk()->viewData('reviewToken');
        $this->post(route('returns.transition', $return), ['action' => 'return_for_correction', 'review_token' => $oldToken, 'reason' => 'Please verify enrolment.'])->assertSessionHasNoErrors();
        $this->actingAs($rep)->post(route('returns.form.save', [$return, 'enrolment']), ['data' => ['classes' => ['year_1' => ['boys' => 35, 'girls' => 15]], 'community' => []]])->assertSessionHasNoErrors();
        $this->assertSame(35, $return->fresh()->statistical_form['enrolment']['classes']['year_1']['boys']);
        $frozen = $this->actingAs($admin)->get(route('returns.show', $return))->assertOk()->viewData('return');
        $this->assertSame(10, $frozen->statistical_form['enrolment']['classes']['year_1']['boys']);
        $this->assertSame([], $frozen->statistical_form_drafts);
        $this->actingAs($admin)->get(route('returns.form.show', [$return, 'enrolment']))->assertOk()->assertViewHas('data', fn ($data) => $data['classes']['year_1']['boys'] === 10);
        $this->actingAs($rep)->post(route('returns.transition', $return), $submit())->assertSessionHasNoErrors();
        $this->assertSame(2, SubmissionVersion::where('school_return_id', $return->id)->count());
        $this->assertDatabaseHas('notification_records', ['user_id' => $admin->id, 'subject' => 'Resubmission received']);
        $this->actingAs($admin)->post(route('returns.transition', $return), ['action' => 'approve', 'review_token' => $oldToken])->assertSessionHasErrors('return');
        $this->assertSame('locked', $return->fresh()->status);
        $token = $this->get(route('returns.show', $return))->assertOk()->viewData('reviewToken');
        $this->post(route('returns.transition', $return), ['action' => 'approve', 'review_token' => $token])->assertSessionHasNoErrors();
        $this->assertSame('approved', $return->fresh()->status);
        $this->assertSame($hash, $first->fresh()->content_hash);
        $count = NotificationRecord::count();
        $this->actingAs($rep)->post(route('returns.transition', $return), $submit())->assertSessionHasNoErrors();
        $this->assertSame($count, NotificationRecord::count());
        $this->assertSame(2, SubmissionVersion::where('school_return_id', $return->id)->count());
    }

    public function test_window_assignments_can_add_schools_but_cannot_remove_submitted_schools(): void
    {
        [$admin,$rep,$return] = $this->context();
        $period = $return->reportingPeriod;
        $payload = ['title' => $period->title, 'opening_date' => now()->subDay()->toDateString(), 'closing_date' => now()->addDay()->toDateString(), 'status' => 'open', 'update_assignments' => 1, 'school_ids' => [$return->school_id]];
        $this->actingAs($admin)->get(route('reporting-periods.edit', $period))->assertOk()->assertSee('Participating schools')->assertSee('Review school');
        $this->put(route('reporting-periods.update', $period), $payload)->assertSessionHasNoErrors();
        $this->assertTrue($period->schools()->where('schools.id', $return->school_id)->exists());
        $payload['school_ids'] = [];
        $this->put(route('reporting-periods.update', $period), $payload)->assertSessionHasErrors('school_ids');
        $this->assertTrue($period->schools()->where('schools.id', $return->school_id)->exists());
    }

    public function test_not_started_return_resumes_as_a_draft_without_duplicate_records(): void
    {
        [$admin,$rep,$return] = $this->context();
        $return->reportingPeriod->schools()->sync([$return->school_id]);
        $return->update(['status' => 'not_started', 'submitted_at' => null, 'submitted_by' => null, 'locked_at' => null]);
        $this->actingAs($rep)->get(route('returns.index'))->assertOk()->assertSee('Start form');
        $this->post(route('returns.start', [$return->reportingPeriod, $return->school]))->assertSessionHasNoErrors()->assertRedirect(route('returns.form.introduction', $return));
        $this->assertSame('draft', $return->fresh()->status);
        $this->assertSame(1, SchoolReturn::where('school_id', $return->school_id)->count());
        $this->actingAs($admin)->get(route('returns.show', $return))->assertForbidden();
    }
}
