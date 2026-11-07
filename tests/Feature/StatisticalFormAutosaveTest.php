<?php

namespace Tests\Feature;

use App\Models\ReportingPeriod;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Services\StatisticalFormQualityService;
use App\Support\StatisticalForm;
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

    public function test_nursery_enrolment_is_available_only_to_nursery_and_primary_schools(): void
    {
        $return = $this->draft();
        foreach (['nursery', 'primary'] as $level) {
            $return->school->update(['school_level' => $level]);
            $this->get(route('returns.form.show', [$return, 'enrolment']))->assertOk()->assertSee('3(b) Enrolment in the Nursery section (in nursery schools or primary schools with nursery section)');
        }
        foreach (['secondary', 'vocational_technical', 'tertiary'] as $level) {
            $return->school->update(['school_level' => $level]);
            $this->get(route('returns.form.show', [$return, 'enrolment']))->assertOk()->assertDontSee('3(b) Enrolment in the Nursery section (in nursery schools or primary schools with nursery section)');
            $data = ['nursery' => ['baby' => ['boys' => '10', 'girls' => '12']]];
            $this->postJson(route('returns.form.autosave', [$return, 'enrolment']), ['revision' => 0, 'data' => $data])->assertUnprocessable();
            $this->post(route('returns.form.save', [$return, 'enrolment']), ['data' => $data])->assertSessionHasErrors('data.nursery');
        }
        $this->post(route('returns.form.save', [$return, 'enrolment']), ['data' => ['classes' => ['year_1' => ['boys' => '10', 'girls' => '12']]]])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('nursery', $return->fresh()->statistical_form['enrolment']);
        $return->update(['status' => 'locked', 'submitted_at' => now()]);
        $this->get(route('returns.show', $return))->assertOk()->assertDontSee('3(b) Enrolment in the Nursery section (in nursery schools or primary schools with nursery section)');
    }

    public function test_examination_tables_follow_the_registered_school_level(): void
    {
        $return = $this->draft();
        foreach (['primary' => ['ple'], 'secondary' => ['uce_scores', 'uce_results', 'uace'], 'vocational_technical' => ['vocational'], 'nursery' => [], 'tertiary' => []] as $level => $allowed) {
            $return->school->update(['school_level' => $level]);
            $this->get(route('returns.form.show', [$return, 'academic']))->assertOk()
                ->assertViewHas('section', fn ($section) => array_keys($section['tables']) === $allowed);
            foreach (array_diff(['ple', 'uce_scores', 'uce_results', 'uace', 'vocational'], $allowed) as $table) {
                $definitions = StatisticalForm::sections()['academic']['tables'][$table];
                $row = isset($definitions['rows']) ? array_key_first($definitions['rows']) : 0;
                $column = array_key_first($definitions['columns']);
                $value = $column === 'grade' ? 'Distinction' : '5';
                $data = [$table => [$row => [$column => $value]]];
                $this->post(route('returns.form.save', [$return, 'academic']), ['data' => $data])->assertSessionHasErrors('data.'.$table);
                $this->postJson(route('returns.form.autosave', [$return, 'academic']), ['revision' => 0, 'data' => $data])->assertUnprocessable();
            }
        }
    }

    public function test_water_reliability_uses_yes_no_and_explanation_only_for_no(): void
    {
        $return = $this->draft();
        $url = route('returns.form.autosave', [$return, 'utilities']);
        $this->postJson($url, ['revision' => 0, 'data' => ['water_reliable' => 'Yes', 'water_explanation' => 'Old explanation']])->assertOk();
        $this->assertArrayNotHasKey('water_explanation', $return->fresh()->statistical_form['utilities']);
        $page = $this->get(route('returns.form.show', [$return, 'utilities']))->assertOk()->assertSee('class="source-yes-no"', false);
        $this->assertMatchesRegularExpression('/source-field-water_explanation"\s+hidden/', $page->getContent());
        $this->postJson($url, ['revision' => 1, 'data' => ['water_reliable' => 'No', 'water_explanation' => 'Supply is seasonal']])->assertOk();
        $this->assertSame('Supply is seasonal', $return->fresh()->statistical_form['utilities']['water_explanation']);
        $page = $this->get(route('returns.form.show', [$return, 'utilities']))->assertOk()->assertSee('Supply is seasonal');
        $this->assertDoesNotMatchRegularExpression('/source-field-water_explanation"\s+hidden/', $page->getContent());
    }

    public function test_religion_shares_enrolment_page_and_saves_without_changing_existing_storage(): void
    {
        $return = $this->draft();
        $return->update(['statistical_form' => ['religion' => ['community' => ['learners' => ['catholics' => 15]]]]]);
        $this->get(route('returns.form.show', [$return, 'enrolment']))->assertOk()->assertSee('Religious affiliation')
            ->assertViewHas('data', fn ($data) => $data['community']['learners']['catholics'] === 15);
        $data = ['classes' => ['year_1' => ['boys' => '12', 'girls' => '8']], 'community' => ['learners' => ['catholics' => '20']]];
        $this->postJson(route('returns.form.autosave', [$return, 'enrolment']), ['revision' => 0, 'data' => $data])->assertOk();
        $saved = $return->fresh()->statistical_form;
        $this->assertSame('20', $saved['religion']['community']['learners']['catholics']);
        $this->assertSame('12', $saved['enrolment']['classes']['year_1']['boys']);
        $this->assertArrayNotHasKey('community', $saved['enrolment']);
        $this->post(route('returns.form.save', [$return, 'enrolment']), ['draft_revision' => 1, 'navigate' => 'next', 'data' => $data])
            ->assertSessionHasNoErrors()->assertRedirect(route('returns.form.show', [$return, 'academic']));
        $this->get(route('returns.form.show', [$return, 'religion']))->assertOk()->assertViewHas('sectionKey', 'enrolment');
    }

    public function test_draft_checks_stay_hidden_until_review_or_an_unsuccessful_submission_attempt(): void
    {
        $return = $this->draft();
        $return->update(['statistical_form' => ['identity' => ['school_name' => 'Demo School Account']]]);
        $page = route('returns.form.show', [$return, 'declaration']);
        $this->get(route('returns.form.show', [$return, 'identity']))->assertOk()->assertDontSee('Draft in progress')->assertDontSee('aria-labelledby="mobile-readiness-error-title"', false);
        $this->get($page)->assertOk()->assertSee('items needing attention');
        $this->from($page)->post(route('returns.transition', $return), ['action' => 'submit', 'acknowledged' => 1, 'review_fingerprint' => StatisticalForm::reviewFingerprint($return->fresh())])
            ->assertSessionHasErrors('return')->assertSessionHas('submission_checks_for', $return->id)->assertRedirect($page);
        $this->assertSame('draft', $return->fresh()->status);
        $this->get($page)->assertOk()->assertSee('items needing attention')->assertSee('Correct the items listed above before submitting.');
    }

    public function test_stale_tab_cannot_overwrite_a_newer_section_draft(): void
    {
        $return = $this->draft();
        $url = route('returns.form.autosave', [$return, 'enrolment']);
        $this->postJson($url, ['revision' => 0, 'data' => ['classes' => ['year_1' => ['boys' => '12', 'girls' => '8']]]])->assertOk();
        $this->postJson($url, ['revision' => 0, 'data' => ['classes' => ['year_1' => ['boys' => '99']]]])->assertStatus(409);
        $this->assertSame('12', $return->fresh()->statistical_form['enrolment']['classes']['year_1']['boys']);
        $this->post(route('returns.form.save', [$return, 'enrolment']), ['draft_revision' => 0, 'data' => ['classes' => ['year_1' => ['boys' => '99']]]])->assertSessionHasErrors('draft_revision');
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
