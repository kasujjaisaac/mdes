<?php

namespace Tests\Unit;

use App\Models\School;
use App\Models\SchoolReturn;
use App\Services\DiocesanDashboardService;
use App\Support\StatisticalForm;
use Tests\TestCase;

class DiocesanDashboardServiceTest extends TestCase
{
    private function record(array $form): array
    {
        $return = new SchoolReturn(['statistical_form' => $form]);
        $return->setRelation('school', new School(['official_name' => 'Test', 'school_level' => 'primary']));

        return app(DiocesanDashboardService::class)->record($return);
    }

    public function test_shortages_do_not_cancel_and_ratios_are_weighted_on_paired_schools(): void
    {
        $records = collect([
            $this->record(['enrolment' => ['classes' => [['boys' => 60, 'girls' => 40]]], 'teaching' => ['members' => [['name' => 'A'], ['name' => 'B']]], 'facilities' => ['items' => ['desk_capacity' => ['quantity' => 20]]]]),
            $this->record(['enrolment' => ['classes' => [['boys' => 5, 'girls' => 5]]], 'teaching' => ['members' => [['name' => 'C']]], 'facilities' => ['items' => ['desk_capacity' => ['quantity' => 500]]]]),
            $this->record(['enrolment' => ['classes' => [['boys' => 100, 'girls' => 100]]]]),
        ]);
        $s = app(DiocesanDashboardService::class)->summary($records);
        $this->assertSame(80, $s['shortfall']['value']);
        $this->assertSame(2, $s['shortfall']['reported']);
        $this->assertSame(36.7, $s['teachers']['ratio']);
        $this->assertSame(2, $s['teachers']['paired']);
        $this->assertSame(310, $s['learners']['value']);
    }

    public function test_missing_values_and_partial_enrolment_are_not_zero(): void
    {
        $r = $this->record(['enrolment' => ['classes' => [['boys' => 10, 'girls' => null]]], 'facilities' => ['items' => ['computers' => ['quantity' => '']]]]);
        $this->assertNull($r['learners']);
        $this->assertNull($r['teachers']);
        $s = app(DiocesanDashboardService::class)->summary(collect([$r]));
        $this->assertNull($s['computers']['value']);
        $this->assertNull($s['water']['value']);
        $this->assertSame(0, $s['water']['reported']);
        $zero = $this->record(['teaching' => ['members' => []], 'facilities' => ['items' => ['computers' => ['quantity' => 0]]]]);
        $this->assertSame(0, $zero['teachers']);
        $this->assertSame(0, $zero['computers']);
    }

    public function test_nursery_and_class_rows_are_both_counted_and_zero_stances_are_visible(): void
    {
        $r = $this->record(['enrolment' => ['classes' => ['same' => ['boys' => 20, 'girls' => 30]], 'nursery' => ['same' => ['boys' => 5, 'girls' => 5]]],
            'facilities' => ['items' => ['boys_toilets' => ['quantity' => 0], 'girls_toilets' => ['quantity' => 2]]]]);
        $s = app(DiocesanDashboardService::class)->summary(collect([$r]));
        $this->assertSame(60, $r['learners']);
        $this->assertSame(1, $s['sanitation']['boys']['zero']);
        $this->assertNull($s['sanitation']['boys']['ratio']);
        $this->assertSame(17.5, $s['sanitation']['girls']['ratio']);
    }

    public function test_exam_score_counts_are_separate_and_partial_distributions_are_excluded(): void
    {
        $academic = [];
        foreach (['ple' => 'candidates', 'uce_results' => 'students', 'uce_scores' => 'scores', 'uace' => 'candidates'] as $key => $column) {
            foreach (array_keys(StatisticalForm::sections()['academic']['tables'][$key]['rows']) as $row) {
                $academic[$key][$row][$column] = 0;
            }
        }
        $academic['uce_scores']['A']['scores'] = 80;
        $academic['uce_results']['result_1']['students'] = 10;
        $academic['ple']['division_1']['candidates'] = 5;
        $result = app(DiocesanDashboardService::class)->exams(collect([$this->record(['academic' => $academic]), $this->record(['academic' => ['ple' => ['division_1' => ['candidates' => 500]]]])]));
        $this->assertSame(80, $result['uce_scores']['total']);
        $this->assertSame(10, $result['uce_results']['total']);
        $this->assertSame(5, $result['ple']['total']);
        $this->assertSame(1, $result['ple']['reported']);
    }
}
