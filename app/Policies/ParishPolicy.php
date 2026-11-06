<?php

namespace App\Policies;

use App\Models\Parish;
use App\Models\User;

class ParishPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('diocesan-structure.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('diocesan-structure.manage');
    }

    public function update(User $user, Parish $parish): bool
    {
        return $user->hasPermission('diocesan-structure.manage');
    }
}
