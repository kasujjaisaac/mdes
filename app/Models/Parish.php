<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['diocese_id', 'name', 'code', 'telephone', 'email', 'contact_person', 'notes', 'is_active'])]
class Parish extends Model
{
    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function jurisdictionAssignments(): HasMany
    {
        return $this->hasMany(JurisdictionAssignment::class);
    }
}
