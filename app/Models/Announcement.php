<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['created_by', 'title', 'body', 'target_scope', 'target_filters', 'requires_acknowledgement', 'published_at', 'publication_key'])]
class Announcement extends Model
{
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries()
    {
        return $this->hasMany(NotificationRecord::class);
    }

    protected function casts(): array
    {
        return ['target_filters' => 'array', 'requires_acknowledgement' => 'boolean', 'published_at' => 'datetime'];
    }
}
