<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'is_national', 'is_active', 'configuration'])]
class ExaminationType extends Model
{
    protected function casts(): array
    {
        return [
            'is_national' => 'boolean',
            'is_active' => 'boolean',
            'configuration' => 'array',
        ];
    }

    public function gradingScales(): HasMany
    {
        return $this->hasMany(GradingScale::class);
    }
}
