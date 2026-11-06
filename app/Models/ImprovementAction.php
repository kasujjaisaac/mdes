<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['improvement_plan_id', 'activity', 'responsible_person', 'due_date', 'expected_output', 'estimated_budget', 'support_required', 'status', 'percent_complete', 'progress_notes'])]
class ImprovementAction extends Model
{
    protected function casts(): array { return ['due_date' => 'date', 'estimated_budget' => 'decimal:2']; }
    public function plan(): BelongsTo { return $this->belongsTo(ImprovementPlan::class, 'improvement_plan_id'); }
}
