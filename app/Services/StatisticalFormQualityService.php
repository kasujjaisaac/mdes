<?php
namespace App\Services;
use App\Models\SchoolReturn;
use App\Support\StatisticalForm;

class StatisticalFormQualityService
{
    public function errors(SchoolReturn $return): array
    {
        $form = $return->statistical_form ?? [];
        $errors = [];
        foreach (StatisticalForm::sections() as $key => $section) {
            if (! array_key_exists($key, $form)) $errors[$key] = $section['title'].' must be reviewed and saved (even if not applicable).';
        }
        foreach (['date','school_name','level','funding','gender','boarding','headteacher_name'] as $field) {
            if (blank($form['identity'][$field] ?? null)) $errors['identity_'.$field] = 'School particulars: '.str_replace('_',' ',$field).' is required.';
        }
        $rows = array_merge(array_values($form['enrolment']['classes'] ?? []), array_values($form['enrolment']['nursery'] ?? []));
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
        foreach (['headteacher_name','priest_name'] as $key) if (blank($form['declaration'][$key] ?? null)) $errors['declaration_'.$key] = 'Declaration: '.str_replace('_',' ',$key).' is required.';
        return $errors;
    }
}
