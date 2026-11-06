<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_id', 'school_return_id', 'sender_id', 'subject', 'user_id', 'type', 'severity', 'message', 'status', 'read_at'])]
class NotificationRecord extends Model
{
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function schoolReturn()
    {
        return $this->belongsTo(SchoolReturn::class);
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
