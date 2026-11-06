<?php

namespace App\Services;

use App\Models\SchoolReturn;
use App\Support\StatisticalForm;
use Illuminate\Support\Collection;

class DiocesanDashboardService
{
    public function number(mixed $value): ?int
    {
        return is_numeric($value) && $value >= 0 ? (int) $value : null;
    }

    public function record(SchoolReturn $return): array
    {
        $form = $return->statistical_form ?? [];
        $rows = array_merge(array_values($form['enrolment']['classes'] ?? []), array_values($form['enrolment']['nursery'] ?? []));
        $enrolmentComplete = count($rows) > 0 && collect($rows)->every(fn ($r) => $this->number($r['boys'] ?? null) !== null && $this->number($r['girls'] ?? null) !== null);
        $boys = $enrolmentComplete ? collect($rows)->sum('boys') : null;
        $girls = $enrolmentComplete ? collect($rows)->sum('girls') : null;
        $members = data_get($form, 'teaching.members');
        $teachers = is_array($members) && collect($members)->every(fn ($m) => filled($m['name'] ?? null)) ? count($members) : null;
        $record = ['return' => $return, 'school' => $return->school, 'boys' => $boys, 'girls' => $girls, 'learners' => $enrolmentComplete ? $boys + $girls : null,
            'teachers' => $teachers, 'members' => $members ?? [], 'water' => data_get($form, 'utilities.water_reliable'), 'form' => $form];
        $nursery = $form['enrolment']['nursery'] ?? [];
        $record['nursery'] = count($nursery) && collect($nursery)->every(fn ($r) => $this->number($r['boys'] ?? null) !== null && $this->number($r['girls'] ?? null) !== null)
            ? collect($nursery)->sum(fn ($r) => (int) $r['boys'] + (int) $r['girls']) : null;
        foreach (['desk_capacity', 'temporary_classrooms', 'semi_permanent_classrooms', 'permanent_classrooms', 'boys_toilets', 'girls_toilets', 'library_books', 'computers'] as $key) {
            $record[$key] = $this->number(data_get($form, "facilities.items.$key.quantity"));
        }
        $record['shortfall'] = $record['learners'] !== null && $record['desk_capacity'] !== null ? max(0, $record['learners'] - $record['desk_capacity']) : null;

        return $record;
    }

    public function summary(Collection $records): array
    {
        $summary = [];
        foreach (['learners', 'boys', 'girls', 'nursery', 'teachers', 'shortfall', 'computers', 'library_books', 'permanent_classrooms', 'semi_permanent_classrooms', 'temporary_classrooms'] as $key) {
            $known = $records->whereNotNull($key);
            $summary[$key] = ['value' => $known->isEmpty() ? null : $known->sum($key), 'reported' => $known->count()];
        }
        $water = $records->whereIn('water', ['Yes', 'No']);
        $unreliable = $water->where('water', 'No');
        $summary['water'] = ['value' => $water->isEmpty() ? null : $unreliable->count(), 'reported' => $water->count(),
            'learners' => $unreliable->whereNotNull('learners')->sum('learners'), 'learner_schools' => $unreliable->whereNotNull('learners')->count()];
        $temporary = $records->whereNotNull('temporary_classrooms');
        $summary['temporary'] = ['value' => $temporary->isEmpty() ? null : $temporary->where('temporary_classrooms', '>', 0)->count(), 'reported' => $temporary->count(), 'rooms' => $temporary->sum('temporary_classrooms')];
        foreach (['teachers', 'computers', 'library_books'] as $key) {
            $paired = $records->filter(fn ($r) => $r['learners'] !== null && $r[$key] !== null);
            $denominator = $key === 'library_books' ? $paired->sum('learners') : $paired->sum($key);
            $numerator = $key === 'library_books' ? $paired->sum($key) : $paired->sum('learners');
            $summary[$key]['ratio'] = $denominator > 0 ? round($numerator / $denominator, 1) : null;
            $summary[$key]['paired'] = $paired->count();
            $summary[$key]['zero'] = $records->whereNotNull($key)->where($key, 0)->count();
        }
        foreach (['boys', 'girls'] as $sex) {
            $paired = $records->filter(fn ($r) => $r[$sex] !== null && $r[$sex.'_toilets'] !== null && $r[$sex] > 0);
            $stances = $paired->sum($sex.'_toilets');
            $summary['sanitation'][$sex] = ['ratio' => $stances > 0 ? round($paired->sum($sex) / $stances, 1) : null, 'reported' => $paired->count(), 'zero' => $paired->where($sex.'_toilets', 0)->count()];
        }
        $staff = $records->whereNotNull('teachers')->flatMap(fn ($r) => $r['members']);
        $summary['licences'] = ['missing' => $staff->filter(fn ($m) => blank($m['license_number'] ?? null))->count(), 'total' => $staff->count(), 'schools' => $records->whereNotNull('teachers')->count()];

        return $summary;
    }

    public function exams(Collection $records): array
    {
        $result = [];
        foreach (['ple' => 'candidates', 'uce_results' => 'students', 'uce_scores' => 'scores', 'uace' => 'candidates'] as $key => $column) {
            $definition = StatisticalForm::sections()['academic']['tables'][$key];
            $known = $records->filter(function ($r) use ($key, $column, $definition) {
                foreach (array_keys($definition['rows']) as $row) {
                    if ($this->number(data_get($r['form'], "academic.$key.$row.$column")) === null) {
                        return false;
                    }
                }

                return true;
            });
            $values = [];
            foreach ($definition['rows'] as $row => $label) {
                $values[$label] = $known->sum(fn ($r) => (int) data_get($r['form'], "academic.$key.$row.$column"));
            }
            $total = array_sum($values);
            $result[$key] = ['values' => $values, 'total' => $total, 'reported' => $known->count(), 'column' => $column];
        }

        return $result;
    }
}
