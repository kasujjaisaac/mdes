<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inspection_id', 'domain', 'severity', 'finding', 'recommendation', 'responsible_person', 'deadline', 'status'])]
class InspectionFinding extends Model
{
    protected function casts(): array
    {
        return ['deadline' => 'date'];
    }

    public function inspection(): BelongsTo { return $this->belongsTo(Inspection::class); }
}
