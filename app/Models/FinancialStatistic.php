<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_return_id', 'school_id', 'budget_year', 'budget_categories', 'income_categories', 'expenditure_categories', 'approved_budget_total', 'income_total', 'expenditure_total', 'variance', 'variance_explanation', 'audited_accounts_available', 'audit_date', 'audit_status'])]
class FinancialStatistic extends Model
{
    protected function casts(): array
    {
        return [
            'budget_categories' => 'array',
            'income_categories' => 'array',
            'expenditure_categories' => 'array',
            'approved_budget_total' => 'decimal:2',
            'income_total' => 'decimal:2',
            'expenditure_total' => 'decimal:2',
            'variance' => 'decimal:2',
            'audited_accounts_available' => 'boolean',
            'audit_date' => 'date',
        ];
    }

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function schoolReturn(): BelongsTo { return $this->belongsTo(SchoolReturn::class); }
}
