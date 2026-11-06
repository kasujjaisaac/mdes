<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['school_id', 'name', 'first_name', 'last_name', 'email', 'telephone', 'designation', 'status', 'password', 'last_login_at'])]
#[Hidden(['password', 'remember_token', 'session_version', 'must_change_password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public static function normalizeUsername(string $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', '', trim($value)));
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $moe = $user->school_id ? School::whereKey($user->school_id)->value('ministry_registration_number') : null;
            if ($moe) {
                $user->username = self::normalizeUsername($moe);

                return;
            }
            $base = mb_substr(self::normalizeUsername(strstr($user->email, '@', true) ?: 'USER'), 0, 76);
            $candidate = $base;
            $suffix = 2;
            while (self::where('username', $candidate)->exists()) {
                $candidate = $base.'-'.$suffix++;
            }
            $user->username = $candidate;
        });
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DEACTIVATED = 'deactivated';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'session_version' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isSchoolRepresentative(): bool
    {
        return ! $this->hasPermission('schools.view.all') && ($this->school_id !== null || $this->roles->contains(fn ($role) => in_array($role->slug, [Role::HEADTEACHER])));
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function jurisdictionAssignments(): HasMany
    {
        return $this->hasMany(JurisdictionAssignment::class);
    }

    public function activeJurisdictionAssignments(): HasMany
    {
        return $this->jurisdictionAssignments()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            });
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains('slug', $role);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole(Role::SUPER_ADMINISTRATOR)) {
            return true;
        }

        $this->loadMissing('roles.permissions');

        return $this->roles->contains(fn ($role) => $role->permissions->contains('slug', $permission));
    }
}
