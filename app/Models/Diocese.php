<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'telephone', 'email', 'address', 'is_active'])]
class Diocese extends Model
{
    public function parishes(): HasMany
    {
        return $this->hasMany(Parish::class);
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }
}
