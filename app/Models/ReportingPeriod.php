<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'type', 'year', 'term', 'instructions', 'opening_date', 'closing_date', 'extension_date', 'lock_date', 'status', 'created_by', 'is_archived', 'annual_year'])]
class ReportingPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_LOCKED = 'locked';

    protected static function booted(): void
    {
        static::saving(function (self $period) {
            $period->annual_year = $period->type === 'annual_census' && ! $period->is_archived ? $period->year : null;
        });
    }

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'opening_date' => 'date',
            'closing_date' => 'date',
            'extension_date' => 'date',
            'lock_date' => 'date',
        ];
    }

    public function isSubmissionOpen(): bool
    {
        $today = now('Africa/Kampala')->toDateString();

        return ! $this->is_archived && $this->status === self::STATUS_OPEN && (! $this->opening_date || $this->opening_date->toDateString() <= $today) && (! ($this->extension_date ?? $this->closing_date) || ($this->extension_date ?? $this->closing_date)->toDateString() >= $today);
    }

    public function allowsRepresentativeDraft(): bool
    {
        $today = now('Africa/Kampala')->toDateString();

        return ! $this->is_archived && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_OPEN]) && (! ($this->extension_date ?? $this->closing_date) || ($this->extension_date ?? $this->closing_date)->toDateString() >= $today);
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class);
    }

    public function schoolReturns(): HasMany
    {
        return $this->hasMany(SchoolReturn::class);
    }
}
