<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_return_id',
    'facility_type',
    'total',
    'functional',
    'damaged',
    'required',
    'condition',
    'priority',
    'water_source',
    'water_reliability',
    'has_electricity',
    'has_internet',
    'computers',
    'fire_safety_available',
    'accessibility_available',
    'security_fenced',
    'high_risk_condition',
    'gap',
    'notes',
])]
class InfrastructureStatistic extends Model
{
    protected function casts(): array
    {
        return [
            'has_electricity' => 'boolean',
            'has_internet' => 'boolean',
            'fire_safety_available' => 'boolean',
            'accessibility_available' => 'boolean',
            'security_fenced' => 'boolean',
            'high_risk_condition' => 'boolean',
        ];
    }

    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }
}
