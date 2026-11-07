<?php
namespace App\Services;
use App\Models\SchoolReturn;
use App\Support\StatisticalForm;
use Illuminate\Support\Facades\Validator;

class StatisticalFormQualityService
{
    public function errors(SchoolReturn $return): array
    {
        $form = $return->statistical_form ?? [];
        $errors = [];
        foreach ($return->statistical_form_drafts ?? [] as $key => $draft) {
            if ($key !== 'declaration' && ($draft['valid'] ?? true) === false) {
                $errors[$key] = (StatisticalForm::sections()[$key]['title'] ?? $key).' has unfinished or invalid entries. Correct them before submitting.';
            }
        }
        foreach (StatisticalForm::sections() as $key => $section) {
            if ($key !== 'declaration' && ! array_key_exists($key, $form)) $errors[$key] = $section['title'].' must be reviewed and saved (even if not applicable).';
        }
        foreach (StatisticalForm::sectionsForReturn($return) as $key => $section) {
            if ($key === 'declaration' || ! array_key_exists($key, $form)) continue;
            $rules = StatisticalForm::rules($section);
            if ($key === 'academic') {
                foreach (array_diff(array_keys(StatisticalForm::sections()['academic']['tables']), StatisticalForm::academicTables($return)) as $table) {
                    $rules['data.'.$table] = ['prohibited'];
                }
            }
            if (Validator::make(['data' => $form[$key]], $rules)->fails()) {
                $errors[$key] = $section['title'].' contains invalid entries. Review and save this section before submitting.';
            }
        }
        if (($form['utilities']['water_reliable'] ?? null) === 'No' && blank($form['utilities']['water_explanation'] ?? null)) {
            $errors['utilities_water_explanation'] = 'Explain why the school water supply is not reliable.';
        }
        foreach (['date','school_name','level','funding','gender','boarding','headteacher_name'] as $field) {
            if (blank($form['identity'][$field] ?? null)) $errors['identity_'.$field] = 'School particulars: '.str_replace('_',' ',$field).' is required.';
        }
        $nursery = StatisticalForm::allowsNursery($return) ? ($form['enrolment']['nursery'] ?? []) : [];
        if (! StatisticalForm::allowsNursery($return) && ! empty($form['enrolment']['nursery'])) {
            $errors['enrolment_nursery'] = 'Nursery enrolment does not apply to this school level. Save the enrolment section again to remove the old nursery entries.';
        }
        $rows = array_merge(array_values($form['enrolment']['classes'] ?? []), array_values($nursery));
        if (! count($rows)) $errors['enrolment'] = 'Record at least one enrolment row, including a confirmed zero where appropriate.';
        foreach ($rows as $row) if (! isset($row['boys'], $row['girls'])) $errors['enrolment_counts'] = 'Each applicable enrolment row needs both boys and girls counts.';
        foreach (['teaching','support'] as $group) foreach ($form[$group]['members'] ?? [] as $row) {
            foreach (['name','gender','payroll','age','qualification','religion','responsibility'] as $key) {
                if (blank($row[$key] ?? null)) $errors[$group.'_'.$key] = ucfirst($group).' staff: '.$key.' is required for each listed member.';
            }
        }
        foreach ($form['facilities']['items'] ?? [] as $row) {
            if (isset($row['quantity']) && blank($row['remarks'] ?? null)) $errors['facility_remarks'] = 'Add remarks to every recorded facility quantity, as requested by the source form.';
        }
        return $errors;
    }
}
