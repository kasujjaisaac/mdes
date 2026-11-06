<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['helpdesk_ticket_id', 'author_id', 'message'])]
class HelpdeskMessage extends Model
{
    public function ticket(): BelongsTo { return $this->belongsTo(HelpdeskTicket::class, 'helpdesk_ticket_id'); }
}
