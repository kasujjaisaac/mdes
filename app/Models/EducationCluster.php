<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'is_active'])]
class EducationCluster extends Model
{
    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function jurisdictionAssignments(): HasMany
    {
        return $this->hasMany(JurisdictionAssignment::class);
    }
}
