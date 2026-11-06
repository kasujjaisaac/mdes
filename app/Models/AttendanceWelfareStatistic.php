<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_return_id', 'enrolled_learners', 'expected_attendance', 'aggregate_attendance', 'attendance_rate', 'retained_learners', 'dropouts', 're_entries', 'transitioned_learners', 'welfare_cases_aggregated', 'trend_explanation'])]
class AttendanceWelfareStatistic extends Model
{
    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }
}
