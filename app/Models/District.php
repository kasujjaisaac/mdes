<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'is_active'])]
class District extends Model
{
    public function subCounties(): HasMany
    {
        return $this->hasMany(SubCounty::class);
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }
}
