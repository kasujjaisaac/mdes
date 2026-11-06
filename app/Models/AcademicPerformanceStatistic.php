<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_return_id',
    'examination_type_id',
    'registered_candidates',
    'candidates_sat',
    'absentees',
    'male_candidates',
    'female_candidates',
    'grade_distribution',
    'pass_rate',
    'weaknesses',
    'intervention_notes',
])]
class AcademicPerformanceStatistic extends Model
{
    protected function casts(): array
    {
        return ['grade_distribution' => 'array'];
    }

    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }

    public function examinationType(): BelongsTo
    {
        return $this->belongsTo(ExaminationType::class);
    }
}
