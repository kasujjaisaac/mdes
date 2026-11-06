<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'document_category_id',
    'school_id',
    'title',
    'issue_date',
    'expiry_date',
    'sensitivity',
    'notes',
    'uploaded_by',
    'uploaded_at',
])]
class ManagedDocument extends Model
{
    use SoftDeletes;

    protected $table = 'managed_documents';

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'uploaded_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasPermission('documents.view')) {
            return $query->whereRaw('1 = 0');
        }
        $query->where(function ($scope) use ($user) {
            $scope->whereNull('school_id')->orWhereHas('school', fn ($schools) => $schools->visibleTo($user));
        });
        if (! $user->hasPermission('documents.manage')) {
            $query->where(fn ($sensitivity) => $sensitivity->whereIn('sensitivity', ['public', 'internal'])->orWhere('uploaded_by', $user->id));
        }

        return $query;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->where('is_current', true);
    }
}
