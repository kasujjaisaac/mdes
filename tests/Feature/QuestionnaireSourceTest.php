<?php

namespace Tests\Feature;

use App\Models\ReportingPeriod;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Support\StatisticalForm;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalSchoolAccountSeeder;
use Database\Seeders\LocalSubmissionWindowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_form_starts_with_the_original_introduction_and_protected_source(): void
    {
        $this->seed([DatabaseSeeder::class, LocalSchoolAccountSeeder::class, LocalSubmissionWindowSeeder::class]);
        $user = User::where('username', 'DEMO-MOE-2026')->firstOrFail();
        $period = ReportingPeriod::where('annual_year', now('Africa/Kampala')->year)->firstOrFail();
        $this->actingAs($user)->post(route('returns.start', [$period, $user->school_id]))->assertRedirect();
        $return = SchoolReturn::where('school_id', $user->school_id)->firstOrFail();
        $this->get(route('returns.form.introduction', $return))->assertOk()
            ->assertSee('STATISTICAL FORM FOR ALL SCHOOLS, 2026')
            ->assertSee('To be filled by the Headteacher. Kindly fill this form and return it, through the Father in charge of schools, to the Diocesan Education Secretariat. Please, provide the most recent information.')
            ->assertSee('i) To collect data on academic performance, enrolment and staffing in schools')
            ->assertSee('ii) To compile information on areas of special need in order to inform project/intervention planning.')
            ->assertSee(route('representative.confirm', $return));
        $this->get(route('returns.form.source', $return))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs(User::factory()->create())->get(route('returns.form.source', $return))->assertForbidden();
    }

    public function test_question_labels_keep_original_numbering_and_full_source_wording(): void
    {
        $sections = StatisticalForm::sections();
        $this->assertSame('h) 2nd Deputy H/M’s Name:', $sections['identity']['fields']['second_deputy_name']['label']);
        $this->assertSame('2. Type of School (Tick the appropriate boxes for items a to d)', $sections['identity']['classification_title']);
        $this->assertSame('3(b) Enrolment in the Nursery section (in nursery schools or primary schools with nursery section)', $sections['enrolment']['tables']['nursery']['label']);
        $this->assertSame('UCE 2025 in accordance with the Competence-based Curriculum’s grading system', $sections['academic']['tables']['uce_scores']['label']);
        $this->assertSame('D Temporary classrooms (with walls of wood, or mud & wattle, or papyrus carpets, or iron sheets, etc.)', $sections['facilities']['tables']['items']['rows']['temporary_classrooms']);
        $this->assertSame('Remarks (These remarks are highly needed)', $sections['facilities']['tables']['items']['columns']['remarks']['label']);
        $this->assertEmpty($sections['teaching']['note']);
        $this->assertEmpty($sections['support']['note']);
        $this->assertSame('Review and submit', $sections['declaration']['title']);
        $this->assertEmpty($sections['declaration']['fields']);
    }
}
