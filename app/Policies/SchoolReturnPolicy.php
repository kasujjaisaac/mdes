<?php

namespace App\Policies;

use App\Models\SchoolReturn;
use App\Models\User;

class SchoolReturnPolicy
{
    public function view(User $user, SchoolReturn $return): bool
    {
        return $user->can('view', $return->school);
    }

    public function update(User $user, SchoolReturn $return): bool
    {
        if ($user->isSchoolRepresentative()) {
            return $user->school_id === $return->school_id && $user->hasPermission('returns.manage.assigned') && in_array($return->status, ['draft', 'returned_for_correction']) && $return->reportingPeriod->allowsRepresentativeDraft();
        }

        return ! $return->isLocked()
            && ($user->hasPermission('returns.manage.all') || ($user->hasPermission('returns.manage.assigned') && $user->can('view', $return->school)));
    }

    public function review(User $user, SchoolReturn $return): bool
    {
        return ! $user->isSchoolRepresentative() && $user->hasPermission('returns.review') && $user->can('view', $return->school);
    }
}
