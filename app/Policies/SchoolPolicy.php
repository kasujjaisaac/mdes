<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('schools.view.all')
            || $user->hasPermission('schools.view.assigned');
    }

    public function view(User $user, School $school): bool
    {
        return $user->isAdministrator() || ($user->isSchoolRepresentative()
            && $user->hasPermission('schools.view.assigned') && $user->school_id === $school->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('schools.manage');
    }

    public function update(User $user, School $school): bool
    {
        return $user->hasPermission('schools.manage');
    }
}
