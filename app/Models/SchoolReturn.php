<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'statistical_form',
    'reporting_period_id',
    'school_id',
    'status',
    'started_by',
    'submitted_by',
    'submitted_at',
    'certified_by',
    'certified_at',
    'approved_by',
    'approved_at',
    'locked_at',
    'correction_reason',
])]
class SchoolReturn extends Model
{
    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CERTIFIED = 'certified';
    public const STATUS_RETURNED_FOR_CORRECTION = 'returned_for_correction';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_LOCKED = 'locked';

    protected function casts(): array
    {
        return [
            'statistical_form' => 'array',
            'profile_snapshot' => 'array',
            'profile_confirmed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'certified_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReturnSection::class);
    }

    public function enrolmentStatistics(): HasMany
    {
        return $this->hasMany(EnrolmentStatistic::class);
    }

    public function attendanceWelfareStatistic(): HasOne
    {
        return $this->hasOne(AttendanceWelfareStatistic::class);
    }

    public function staffStatistic(): HasOne
    {
        return $this->hasOne(StaffStatistic::class);
    }

    public function academicPerformanceStatistics(): HasMany
    {
        return $this->hasMany(AcademicPerformanceStatistic::class);
    }

    public function infrastructureStatistics(): HasMany
    {
        return $this->hasMany(InfrastructureStatistic::class);
    }

    public function catholicIdentityStatistic(): HasOne
    {
        return $this->hasOne(CatholicIdentityStatistic::class);
    }

    public function governanceComplianceStatistic(): HasOne
    {
        return $this->hasOne(GovernanceComplianceStatistic::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(ReturnCertification::class);
    }

    public function qualityFindings(): HasMany
    {
        return $this->hasMany(DataQualityFinding::class);
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
