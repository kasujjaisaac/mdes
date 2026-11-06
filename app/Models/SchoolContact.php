<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_id', 'telephone', 'email', 'postal_address', 'website'])]
class SchoolContact extends Model
{
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
