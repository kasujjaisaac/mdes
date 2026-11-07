<?php

namespace Tests\Feature;

use App\Models\ReportingPeriod;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Services\StatisticalFormQualityService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalSchoolAccountSeeder;
use Database\Seeders\LocalSubmissionWindowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticalFormAutosaveTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): SchoolReturn
    {
        $this->seed([DatabaseSeeder::class, LocalSchoolAccountSeeder::class, LocalSubmissionWindowSeeder::class]);
        $user = User::where('username', 'DEMO-MOE-2026')->firstOrFail();
        $this->actingAs($user);

        $return = (new SchoolReturn)->forceFill(['school_id' => $user->school_id, 'reporting_period_id' => ReportingPeriod::where('annual_year', now('Africa/Kampala')->year)->firstOrFail()->id,
            'status' => 'draft', 'started_by' => $user->id, 'profile_confirmed_at' => now(), 'profile_confirmed_by' => $user->id,
            'profile_snapshot' => ['school_name' => 'Demo School Account', 'registration_number' => 'DEMO-MOE-2026']]);
        $return->save();

        return $return;
    }

    public function test_unfinished_entries_are_saved_and_restored_without_replacing_validated_data(): void
    {
        $return = $this->draft();
        $return->update(['statistical_form' => ['teaching' => ['members' => [['name' => 'Existing teacher', 'age' => '40']]]]]);
        $url = route('returns.form.autosave', [$return, 'teaching']);
        $this->postJson($url, ['revision' => 0, 'data' => ['members' => [0 => ['name' => '', 'age' => '1']]]])
            ->assertOk()->assertJsonPath('revision', 1)->assertJsonPath('valid', false);
        $return->refresh();
        $this->assertSame('1', $return->statistical_form_drafts['teaching']['data']['members'][0]['age']);
        $this->assertSame('Existing teacher', $return->statistical_form['teaching']['members'][0]['name']);
        $this->get(route('returns.form.show', [$return, 'teaching']))->assertOk()
            ->assertViewHas('data', fn ($data) => $data['members'][0]['age'] === '1')->assertViewHas('draftRevision', 1);
        $this->assertArrayHasKey('teaching', app(StatisticalFormQualityService::class)->errors($return));
        $this->postJson($url, ['revision' => 1, 'data' => ['members' => [['name' => 'Completed teacher', 'age' => '40']]]])
            ->assertOk()->assertJsonPath('valid', true);
        $this->assertSame('Completed teacher', $return->fresh()->statistical_form['teaching']['members'][0]['name']);
    }

    public function test_stale_tab_cannot_overwrite_a_newer_section_draft(): void
    {
        $return = $this->draft();
        $url = route('returns.form.autosave', [$return, 'enrolment']);
        $this->postJson($url, ['revision' => 0, 'data' => ['classes' => ['year_1' => ['boys' => '12', 'girls' => '8']]]])->assertOk();
        $this->postJson($url, ['revision' => 0, 'data' => ['classes' => ['year_1' => ['boys' => '99']]]])->assertStatus(409);
        $this->assertSame('12', $return->fresh()->statistical_form['enrolment']['classes']['year_1']['boys']);
        $this->post(route('returns.form.save', [$return, 'enrolment']), ['draft_revision' => 0, 'data' => ['classes' => ['year_1' => ['boys' => '99']]]])->assertStatus(409);
    }

    public function test_autosave_respects_ownership_profile_confirmation_and_submission_lock(): void
    {
        $return = $this->draft();
        $url = route('returns.form.autosave', [$return, 'identity']);
        $this->postJson($url, ['revision' => 0, 'data' => ['school_name' => 'Forged name']])->assertOk();
        $this->assertSame('Demo School Account', $return->fresh()->statistical_form['identity']['school_name']);
        $return->update(['status' => 'locked']);
        $this->postJson($url, ['revision' => 1, 'data' => []])->assertForbidden();
        $return->forceFill(['status' => 'draft', 'profile_confirmed_at' => null])->save();
        $this->postJson($url, ['revision' => 1, 'data' => []])->assertStatus(409);
        $other = User::factory()->create();
        $this->actingAs($other)->postJson($url, ['revision' => 1, 'data' => []])->assertForbidden();
    }

    public function test_section_autosaves_do_not_erase_other_sections_and_reject_unrecognised_fields(): void
    {
        $return = $this->draft();
        $this->postJson(route('returns.form.autosave', [$return, 'enrolment']), ['revision' => 0, 'data' => ['classes' => ['year_1' => ['boys' => '12', 'girls' => '8']]]])->assertOk();
        $this->postJson(route('returns.form.autosave', [$return, 'identity']), ['revision' => 0, 'data' => ['headteacher_name' => 'New headteacher']])->assertOk();
        $this->assertSame('12', $return->fresh()->statistical_form['enrolment']['classes']['year_1']['boys']);
        $this->postJson(route('returns.form.autosave', [$return, 'identity']), ['revision' => 1, 'data' => ['unexpected' => 'value']])->assertUnprocessable();
    }
}
