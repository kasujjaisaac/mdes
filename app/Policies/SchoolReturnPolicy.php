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
            return $user->school_id === $return->school_id && $user->hasPermission('returns.manage.assigned') && in_array($return->status, [SchoolReturn::STATUS_DRAFT, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION]) && ($return->status === 'returned_for_correction' ? ! $return->reportingPeriod->is_archived : $return->reportingPeriod->allowsRepresentativeDraft());
        }

        return false;
    }

    public function review(User $user, SchoolReturn $return): bool
    {
        return ! $user->isSchoolRepresentative() && $user->hasPermission('returns.review') && $user->can('view', $return->school);
    }
}
