<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'parish_id', 'education_cluster_id', 'assignment_type', 'effective_from', 'effective_to', 'is_active', 'notes'])]
class JurisdictionAssignment extends Model
{
    public const TYPE_PARISH_COORDINATOR = 'parish_coordinator';
    public const TYPE_INSPECTOR = 'inspector';

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    public function educationCluster(): BelongsTo
    {
        return $this->belongsTo(EducationCluster::class);
    }
}
