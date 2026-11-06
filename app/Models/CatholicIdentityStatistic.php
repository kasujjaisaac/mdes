<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_return_id',
    'has_chaplaincy',
    'pastoral_contact',
    'mass_frequency',
    'retreats_held',
    'catechesis_available',
    'values_education_available',
    'safeguarding_structure_available',
    'counselling_structure_available',
    'parish_school_engagements',
    'pastoral_programmes',
    'notes',
])]
class CatholicIdentityStatistic extends Model
{
    protected function casts(): array
    {
        return [
            'has_chaplaincy' => 'boolean',
            'catechesis_available' => 'boolean',
            'values_education_available' => 'boolean',
            'safeguarding_structure_available' => 'boolean',
            'counselling_structure_available' => 'boolean',
        ];
    }

    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }
}
