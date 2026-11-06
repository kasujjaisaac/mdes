<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'diocese_id',
    'parish_id',
    'district_id',
    'sub_county_id',
    'education_cluster_id',
    'official_name',
    'diocesan_code',
    'emis_number',
    'ministry_registration_number',
    'examination_centre_number',
    'former_name',
    'school_level',
    'ownership',
    'funding_classification',
    'gender_classification',
    'boarding_status',
    'operational_status',
    'village',
    'latitude',
    'longitude',
    'profile_verified',
    'created_by',
    'updated_by',
])]
class School extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_MERGED = 'merged';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_UNDER_CONSTRUCTION = 'under_construction';
    public const STATUS_UNDER_REVIEW = 'under_review';

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'profile_verified' => 'boolean',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('schools.view.all')) {
            return $query;
        }

        if ($user->isSchoolRepresentative()) return $query->whereKey($user->school_id ?? -1);

        $parishIds = $user->activeJurisdictionAssignments()->pluck('parish_id')->filter()->all();

        return $query->whereIn('parish_id', $parishIds ?: [-1]);
    }

    public function diocese(): BelongsTo
    {
        return $this->belongsTo(Diocese::class);
    }

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function subCounty(): BelongsTo
    {
        return $this->belongsTo(SubCounty::class);
    }

    public function educationCluster(): BelongsTo
    {
        return $this->belongsTo(EducationCluster::class);
    }

    public function contact(): HasOne
    {
        return $this->hasOne(SchoolContact::class);
    }

    public function leadershipHistories(): HasMany
    {
        return $this->hasMany(SchoolLeadershipHistory::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SchoolStatusHistory::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(SchoolIdentifier::class);
    }

    public function schoolReturns(): HasMany
    {
        return $this->hasMany(SchoolReturn::class);
    }
}
