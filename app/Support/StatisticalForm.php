<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class StatisticalForm
{
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

    public static function clean(array $data, array $section): array
    {
        $clean = array_intersect_key($data, $section['fields'] ?? []);
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
