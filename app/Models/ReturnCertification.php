<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_return_id', 'certified_by', 'declaration', 'snapshot', 'certified_at'])]
class ReturnCertification extends Model
{
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'certified_at' => 'datetime',
        ];
    }

    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }

    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }
}
