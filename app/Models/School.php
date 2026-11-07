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
    'registration_date',
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
    protected static function booted(): void
    {
        static::updated(function (School $school) {
            if ($school->wasChanged('ministry_registration_number') && filled($school->ministry_registration_number)) {
                User::where('school_id', $school->id)->update(['username' => User::normalizeUsername($school->ministry_registration_number)]);
            }
        });
    }

    public static function normalizeMoeNumber(?string $value): ?string
    {
        $normalized = mb_strtoupper(preg_replace('/\s+/u', '', trim($value ?? '')));

        return $normalized === '' ? null : $normalized;
    }

    public function setMinistryRegistrationNumberAttribute(?string $value): void
    {
        $this->attributes['ministry_registration_number'] = self::normalizeMoeNumber($value);
    }

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_MERGED = 'merged';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_UNDER_CONSTRUCTION = 'under_construction';

    public const STATUS_UNDER_REVIEW = 'under_review';

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'profile_verified' => 'boolean',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdministrator()) {
            return $query;
        }
        if ($user->hasPermission('schools.view.assigned') && $user->isSchoolRepresentative()) {
            return $query->whereKey($user->school_id ?? -1);
        }

        return $query->whereRaw('1 = 0');
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

    public function currentFirstDeputy(): HasOne
    {
        return $this->hasOne(SchoolLeadershipHistory::class)->where('position', 'first_deputy')->where('is_current', true)->orderByDesc('id');
    }

    public function currentSecondDeputy(): HasOne
    {
        return $this->hasOne(SchoolLeadershipHistory::class)->where('position', 'second_deputy')->where('is_current', true)->orderByDesc('id');
    }

    public function currentHeadteacher(): HasOne
    {
        return $this->hasOne(SchoolLeadershipHistory::class)->where('position', 'headteacher')->where('is_current', true)->orderByDesc('id');
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
