<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['classification', 'implemented_modules', 'security_notes', 'workflow_notes', 'outstanding_decisions', 'technical_debt', 'reviewed_by', 'reviewed_at'])]
class ProductionReadinessReview extends Model
{
    protected function casts(): array
    {
        return [
            'implemented_modules' => 'array',
            'security_notes' => 'array',
            'workflow_notes' => 'array',
            'outstanding_decisions' => 'array',
            'technical_debt' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
