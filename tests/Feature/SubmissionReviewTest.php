<?php

namespace Tests\Feature;

use App\Models\Diocese;
use App\Models\District;
use App\Models\Parish;
use App\Models\Permission;
use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
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
        $this->post(route('returns.transition', $return), ['action' => 'approve'])->assertSessionHasNoErrors();
        $return->refresh();
        $this->assertSame('approved', $return->status);
        $this->assertSame($admin->id, $return->approved_by);
        $this->assertNotNull($return->approved_at);
        $this->assertSame($before, $return->statistical_form);
        $this->assertNotNull($return->locked_at);
        $this->assertDatabaseHas('audit_events', ['action' => 'submission.approve', 'auditable_id' => $return->id]);
        $this->assertDatabaseHas('notification_records', ['user_id' => $rep->id, 'subject' => 'Submission approved']);
        $this->get(route('returns.show', $return))->assertOk()->assertSee('Review history')->assertDontSee('Approve submission')->assertDontSee('class="button review-return-trigger"', false);
        $this->post(route('returns.transition', $return), ['action' => 'approve'])->assertSessionHasErrors('return');
        $this->post(route('returns.transition', $return), ['action' => 'return_for_correction', 'reason' => 'No'])->assertSessionHasErrors('return');
        $this->actingAs($rep)->post(route('returns.transition', $return), ['action' => 'approve'])->assertForbidden();
        $this->post(route('returns.form.save', [$return, 'enrolment']), ['data' => []])->assertForbidden();
    }

    public function test_return_requires_reason_notifies_school_and_admin_cannot_edit_returned_form(): void
    {
        [$admin,$rep,$return] = $this->context();
        $this->actingAs($admin)->post(route('returns.transition', $return), ['action' => 'return_for_correction'])->assertSessionHasErrors('reason');
        $this->post(route('returns.transition', $return), ['action' => 'return_for_correction', 'reason' => 'Please verify the learner count.'])->assertSessionHasNoErrors();
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
        $this->actingAs($admin)->post(route('returns.transition', $return), ['action' => 'approve'])->assertSessionHasErrors('return');
        $this->post(route('returns.transition', $return), ['action' => 'return_for_correction', 'reason' => 'Check'])->assertSessionHasErrors('return');
        $this->assertSame('locked', $return->fresh()->status);
    }

    public function test_reviewer_scope_is_enforced_for_queue_and_review_actions(): void
    {
        [$admin, $rep, $return] = $this->context();
        $role = Role::create(['slug' => 'scoped-reviewer', 'name' => 'Scoped reviewer']);
        $role->permissions()->sync(Permission::whereIn('slug', ['returns.view', 'returns.review', 'schools.view.assigned'])->pluck('id'));
        $reviewer = User::factory()->create();
        $reviewer->roles()->attach($role);
        $this->actingAs($reviewer)->get(route('returns.index'))->assertOk()->assertViewHas('returns', fn ($rows) => $rows->total() === 0);
        $this->post(route('returns.transition', $return), ['action' => 'approve'])->assertForbidden();
        $this->post(route('returns.transition', $return), ['action' => 'return_for_correction', 'reason' => 'No scope'])->assertForbidden();
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
}
