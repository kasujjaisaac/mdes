<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_return_id', 'class_level', 'boys', 'girls', 'total', 'day_scholars', 'boarders', 'repeaters', 'transfers_in', 'transfers_out', 'dropouts', 're_entries', 'completions', 'notes'])]
class EnrolmentStatistic extends Model
{
    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }
}
