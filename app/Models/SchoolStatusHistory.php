<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'previous_status', 'new_status', 'effective_date', 'reason', 'responsible_user_id'])]
class SchoolStatusHistory extends Model
{
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
