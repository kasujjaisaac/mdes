<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['school_id', 'source_type', 'source_id', 'problem_statement', 'domain', 'baseline', 'target', 'success_indicator', 'due_date', 'status'])]
class ImprovementPlan extends Model
{
    protected function casts(): array { return ['due_date' => 'date']; }
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function source(): MorphTo { return $this->morphTo(); }
    public function actions(): HasMany { return $this->hasMany(ImprovementAction::class); }
}
