<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['school_id', 'source_type', 'source_id', 'category', 'description', 'justification', 'urgency', 'beneficiaries', 'estimated_cost', 'school_contribution', 'review_status', 'funding_status', 'priority', 'review_notes'])]
class DevelopmentNeed extends Model
{
    protected function casts(): array { return ['estimated_cost' => 'decimal:2', 'school_contribution' => 'decimal:2']; }
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function source(): MorphTo { return $this->morphTo(); }
    public function project(): HasOne { return $this->hasOne(DevelopmentProject::class); }
}
