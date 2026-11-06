<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['district_id', 'name', 'code', 'is_active'])]
class SubCounty extends Model
{
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }
}
