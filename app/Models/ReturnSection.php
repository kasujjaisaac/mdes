<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_return_id', 'section', 'status', 'validation_errors', 'validation_warnings', 'completed_at'])]
class ReturnSection extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETE = 'complete';

    protected function casts(): array
    {
        return [
            'validation_errors' => 'array',
            'validation_warnings' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }
}
