<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'inspection_template_id', 'inspector_id', 'type', 'purpose', 'planned_date', 'status', 'checklist_responses', 'summary'])]
class Inspection extends Model
{
    protected function casts(): array
    {
        return ['planned_date' => 'date', 'checklist_responses' => 'array'];
    }

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function template(): BelongsTo { return $this->belongsTo(InspectionTemplate::class, 'inspection_template_id'); }
    public function findings(): HasMany { return $this->hasMany(InspectionFinding::class); }
}
