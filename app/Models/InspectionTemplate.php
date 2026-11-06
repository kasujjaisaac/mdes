<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'version', 'checklist_items', 'is_active'])]
class InspectionTemplate extends Model
{
    protected function casts(): array
    {
        return ['checklist_items' => 'array', 'is_active' => 'boolean'];
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }
}
