<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['examination_type_id', 'grade', 'sort_order', 'is_passing'])]
class GradingScale extends Model
{
    protected function casts(): array
    {
        return ['is_passing' => 'boolean'];
    }

    public function examinationType(): BelongsTo
    {
        return $this->belongsTo(ExaminationType::class);
    }
}
