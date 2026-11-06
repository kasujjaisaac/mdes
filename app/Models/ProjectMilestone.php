<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['development_project_id', 'title', 'due_date', 'status', 'evidence'])]
class ProjectMilestone extends Model
{
    protected function casts(): array { return ['due_date' => 'date']; }
    public function project(): BelongsTo { return $this->belongsTo(DevelopmentProject::class, 'development_project_id'); }
}
