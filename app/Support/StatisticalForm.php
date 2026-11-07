<?php

namespace App\Support;

use App\Models\SchoolReturn;
use Illuminate\Validation\Rule;

class StatisticalForm
{
    public static function schoolLevel(SchoolReturn $return): string
    {
        // Confirmed snapshots govern old returns even after a school changes category.
        $level = $return->profile_snapshot['level'] ?? ($return->submitted_at ? ($return->statistical_form['identity']['level'] ?? null) : null);
        return match ($level) {
            'Pre-primary', 'Nursery' => 'nursery', 'Primary' => 'primary',
            'Secondary' => 'secondary', 'Vocational/Technical', 'Voc./Tech.' => 'vocational_technical',
            'Tertiary' => 'tertiary', default => $return->school->school_level,
        };
    }

    public static function reviewFingerprint(SchoolReturn $return): string
    {
        return hash_hmac('sha256', json_encode([
            $return->id, $return->status, $return->statistical_form,
            $return->statistical_form_drafts, $return->profile_snapshot,
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public static function allowsNursery(SchoolReturn $return): bool
    {
        return in_array(self::schoolLevel($return), ['nursery', 'primary'], true);
    }

    public static function academicTables(SchoolReturn $return): array
    {
        return match (self::schoolLevel($return)) {
            'primary' => ['ple'],
            'secondary' => ['uce_scores', 'uce_results', 'uace'],
            'vocational_technical' => ['vocational'],
            default => [],
        };
    }

    public static function sectionsForReturn(SchoolReturn $return): array
    {
        $sections = self::sections();
        $sections['academic']['tables'] = array_intersect_key($sections['academic']['tables'], array_flip(self::academicTables($return)));
        if (! self::allowsNursery($return)) {
            unset($sections['enrolment']['tables']['nursery']);
        }
        $sections['enrolment']['tables']['community'] = self::sections()['religion']['tables']['community'];
        unset($sections['religion']);

        return $sections;
    }

    public static function savedPageCount(SchoolReturn $return): int
    {
        $form = $return->statistical_form ?? [];

        return collect(array_keys(self::sectionsForReturn($return)))->reject(fn ($key) => $key === 'declaration')->filter(fn ($key) => array_key_exists($key, $form)
            && ($key !== 'enrolment' || array_key_exists('religion', $form)))->count();
    }

    public static function sectionPayloads(string $section, array $data, bool $hasCommunity): array
    {
        if ($section !== 'enrolment') {
            return [$section => $data];
        }
        $community = $data['community'] ?? [];
        unset($data['community']);

        return ['enrolment' => $data, ...($hasCommunity ? ['religion' => ['community' => $community]] : [])];
    }

    public static function sections(): array
    {
        static $sections;

        return $sections ??= json_decode(file_get_contents(resource_path('forms/statistical-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function rules(array $section): array
    {
        $rules = ['data' => ['present', 'array']];
        foreach ($section['fields'] ?? [] as $key => $field) {
            $rules["data.$key"] = self::fieldRules($field);
            if ($field['type'] === 'multiple') {
                $rules["data.$key.*"] = ['string', Rule::in($field['options'])];
            }
        }
        foreach ($section['tables'] ?? [] as $key => $table) {
            $rules["data.$key"] = ['sometimes', 'array', 'max:500'];
            $rules["data.$key.*"] = ['array:'.implode(',', array_keys($table['columns']))];
            $rows = isset($table['rows']) ? array_keys($table['rows']) : ['*'];
            if (isset($table['rows'])) {
                $rules["data.$key"][] = 'array:'.implode(',', $rows);
            }
            foreach ($rows as $row) {
                foreach ($table['columns'] as $column => $field) {
                    $rules["data.$key.$row.$column"] = self::fieldRules($field);
                    // Only land is fractional; rooms, books, equipment and stances are whole counts.
                    if ($key === 'items' && $row !== 'land_acres' && $column === 'quantity') {
                        $rules["data.$key.$row.$column"] = ['nullable', 'integer', 'min:0', 'max:100000000'];
                    }
                }
            }
        }

        return $rules;
    }

    private static function fieldRules(array $field): array
    {
        return match ($field['type']) {
            'integer' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'decimal' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'age' => ['nullable', 'integer', 'min:16', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'select' => ['nullable', Rule::in($field['options'])],
            'multiple' => ['nullable', 'array', 'max:10'],
            default => ['nullable', 'string', 'max:'.($field['type'] === 'textarea' ? 3000 : 255)],
        };
    }

    /** Preserve unfinished entries without treating them as validated submission data. */
    public static function draftRules(array $section): array
    {
        $rules = self::rules($section);
        $rules['data'] = ['present', 'array:'.implode(',', [...array_keys($section['fields'] ?? []), ...array_keys($section['tables'] ?? []), '_section'])];
        $rules['data._section'] = ['nullable', 'string', 'max:80'];
        foreach ($rules as $key => $constraints) {
            if (! collect($constraints)->contains(fn ($rule) => is_string($rule) && ($rule === 'array' || str_starts_with($rule, 'array:')))) {
                $rules[$key] = ['nullable', 'string', 'max:3000'];
            }
        }

        return $rules;
    }

    public static function requiredForSubmission(string $section, string $field, ?string $table = null): bool
    {
        if ($table === null) {
            return match ($section) {
                'identity' => in_array($field, ['date', 'school_name', 'level', 'funding', 'gender', 'boarding', 'headteacher_name']),
                'utilities' => $field === 'water_explanation',
                default => false,
            };
        }
        if ($section === 'enrolment') {
            return in_array($field, ['boys', 'girls']);
        }
        if (in_array($section, ['teaching', 'support']) && $table === 'members') {
            return in_array($field, ['name', 'gender', 'payroll', 'age', 'qualification', 'religion', 'responsibility']);
        }
        if ($section === 'facilities' && $field === 'remarks') {
            return true;
        }
        $definition = self::sections()[$section]['tables'][$table] ?? [];

        return isset($definition['repeatable']) && $field === (isset($definition['columns']['name']) ? 'name' : 'grade');
    }

    public static function clean(array $data, array $section): array
    {
        $clean = array_intersect_key($data, $section['fields'] ?? []);
        if (isset($section['fields']['water_reliable']) && ($clean['water_reliable'] ?? null) !== 'No') {
            unset($clean['water_explanation']);
        }
        foreach ($section['tables'] ?? [] as $key => $table) {
            $rows = $data[$key] ?? [];
            if (isset($table['rows'])) {
                $rows = array_intersect_key($rows, $table['rows']);
            }
            $clean[$key] = [];
            foreach ($rows as $index => $row) {
                $row = array_intersect_key($row, $table['columns']);
                if (collect($row)->contains(fn ($value) => $value !== null && $value !== '')) {
                    $clean[$key][$index] = $row;
                }
            }
        }

        return $clean;
    }
}
