<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['created_by', 'title', 'body', 'target_scope', 'target_filters', 'requires_acknowledgement', 'published_at'])]
class Announcement extends Model
{
    protected function casts(): array { return ['target_filters' => 'array', 'requires_acknowledgement' => 'boolean', 'published_at' => 'datetime']; }
}
