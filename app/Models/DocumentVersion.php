<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'managed_document_id',
    'version',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
    'reason',
    'is_current',
    'uploaded_by',
    'uploaded_at',
])]
class DocumentVersion extends Model
{
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'uploaded_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ManagedDocument::class, 'managed_document_id');
    }
}
