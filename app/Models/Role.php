<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'description'])]
class Role extends Model
{
    public const SUPER_ADMINISTRATOR = 'system-super-administrator';

    public const HEADTEACHER = 'headteacher';

    public const ACCOUNT_ROLES = [self::SUPER_ADMINISTRATOR, self::HEADTEACHER];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
