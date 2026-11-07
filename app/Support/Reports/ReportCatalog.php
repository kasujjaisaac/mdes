<?php

namespace App\Support\Reports;

use Illuminate\Validation\Rule;

class ReportCatalog
{
    public const TYPES = [
        'annual summary' => 'Diocesan annual education report', 'school registry' => 'Registered schools directory',
        'leadership' => 'School leadership and contacts', 'submission progress' => 'Submission progress',
        'enrolment' => 'Enrolment', 'enrolment rankings' => 'School enrolment rankings',
        'religious affiliation' => 'Religious affiliation', 'examinations' => 'Academic performance',
        'staff' => 'Teaching staff', 'support staff' => 'Support staff', 'staff study programmes' => 'Staff study programmes',
        'infrastructure' => 'Facilities and resources', 'utilities' => 'Water and electricity',
        'planning indicators' => 'Planning indicators', 'year comparison' => 'Year to year comparison',
        'announcements' => 'Announcements and acknowledgement', 'review history' => 'Submission review history',
        'support requests' => 'Support requests', 'user accounts' => 'User accounts and access', 'complete form' => 'Complete school statistical forms',
    ];

    public const FORMATS = ['docx' => 'Word', 'xlsx' => 'Excel', 'pdf' => 'PDF', 'csv' => 'CSV'];

    public static function rules(): array
    {
        return [
            'report_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'year' => ['nullable', 'integer', 'between:2000,2100'], 'comparison_year' => ['nullable', 'integer', 'between:2000,2100', 'different:year'],
            'parish_id' => ['nullable', 'integer', 'exists:parishes,id'], 'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'level' => ['nullable', Rule::in(['nursery', 'primary', 'secondary', 'vocational_technical', 'tertiary'])],
            'funding' => ['nullable', Rule::in(['Government', 'Private'])],
            'residence' => ['nullable', Rule::in(['Day', 'Boarding', 'Both day and boarding'])],
            'gender' => ['nullable', Rule::in(['Boys', 'Girls', 'Mixed'])],
            'basis' => ['nullable', Rule::in(['submitted', 'approved'])],
        ];
    }
}
