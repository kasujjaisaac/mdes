<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_return_id', 'male_teaching_staff', 'female_teaching_staff', 'male_non_teaching_staff', 'female_non_teaching_staff', 'qualified_teachers', 'unqualified_teachers', 'government_payroll_staff', 'private_payroll_staff', 'approved_establishment', 'filled_positions', 'vacancies', 'vacancy_rate', 'pupil_teacher_ratio', 'training_needs', 'housing_gaps'])]
class StaffStatistic extends Model
{
    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }
}
