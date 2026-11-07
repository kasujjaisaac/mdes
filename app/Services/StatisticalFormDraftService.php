<?php

namespace App\Services;

use App\Models\SchoolReturn;
use App\Support\StatisticalForm;
use Illuminate\Support\Facades\Validator;

class StatisticalFormDraftService
{
    public function save(SchoolReturn $return, string $section, array $data, int $revision): array
    {
        $drafts = $return->statistical_form_drafts ?? [];
        abort_unless((int) ($drafts[$section]['revision'] ?? 0) === $revision, 409, 'This section was changed in another tab. Reload before continuing.');
        if ($section === 'identity') {
            $data = [...$data, ...($return->profile_snapshot ?? [])];
        }
        $schema = StatisticalForm::sections()[$section];
        $validator = Validator::make(['data' => $data], StatisticalForm::rules($schema));
        $valid = $validator->passes();
        $clean = StatisticalForm::clean($data, $schema);
        foreach ($schema['tables'] ?? [] as $key => $table) {
            if (isset($table['repeatable'])) {
                $required = isset($table['columns']['name']) ? 'name' : 'grade';
                foreach ($clean[$key] ?? [] as $row) {
                    $valid = $valid && filled($row[$required] ?? null);
                }
            }
        }
        $drafts[$section] = ['data' => $data, 'revision' => $revision + 1, 'saved_at' => now()->toIso8601String(), 'valid' => $valid];
        $return->forceFill(['statistical_form_drafts' => $drafts]);
        if ($valid) {
            $return->forceFill(['statistical_form' => [...($return->statistical_form ?? []), $section => $clean]]);
        }
        $return->save();
        if ($valid) {
            app(StatisticalFormSummaryService::class)->sync($return, $section, $clean);
        }

        return ['revision' => $revision + 1, 'saved_at' => $drafts[$section]['saved_at'], 'valid' => $valid];
    }
}
