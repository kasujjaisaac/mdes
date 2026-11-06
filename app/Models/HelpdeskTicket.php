<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'opened_by', 'assigned_to', 'category', 'priority', 'description', 'status'])]
class HelpdeskTicket extends Model
{
    public function messages(): HasMany { return $this->hasMany(HelpdeskMessage::class); }
}
