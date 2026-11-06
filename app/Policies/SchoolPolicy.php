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
        if ($user->hasPermission('schools.view.all')) {
            return true;
        }

        if (! $user->hasPermission('schools.view.assigned')) {
            return false;
        }

        if ($user->isSchoolRepresentative()) return $user->school_id === $school->id;

        return $user->activeJurisdictionAssignments()
            ->where('parish_id', $school->parish_id)
            ->exists();
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
