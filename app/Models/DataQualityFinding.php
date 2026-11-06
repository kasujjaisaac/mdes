<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_return_id', 'section', 'severity', 'rule', 'message', 'context'])]
class DataQualityFinding extends Model
{
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const INFORMATION = 'information';

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }
}
