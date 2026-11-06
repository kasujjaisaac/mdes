<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['development_need_id', 'school_id', 'title', 'approved_budget', 'funder', 'implementer', 'start_date', 'planned_completion_date', 'status', 'percent_complete'])]
class DevelopmentProject extends Model
{
    protected function casts(): array { return ['approved_budget' => 'decimal:2', 'start_date' => 'date', 'planned_completion_date' => 'date']; }
    public function need(): BelongsTo { return $this->belongsTo(DevelopmentNeed::class, 'development_need_id'); }
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function milestones(): HasMany { return $this->hasMany(ProjectMilestone::class); }
}
