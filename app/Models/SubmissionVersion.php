<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['school_return_id', 'version', 'payload', 'content_hash', 'review_status', 'submitted_by', 'submitted_at', 'reviewed_at', 'reviewed_by', 'correction_reason', 'legacy_import'])]
class SubmissionVersion extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $version) {
            if ($version->isDirty(['school_return_id', 'version', 'payload', 'content_hash', 'submitted_at', 'submitted_by', 'legacy_import'])) {
                throw new \LogicException('Submitted answers and version identity are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'legacy_import' => 'boolean', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function schoolReturn()
    {
        return $this->belongsTo(SchoolReturn::class);
    }
}
