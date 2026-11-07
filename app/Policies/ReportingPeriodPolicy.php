<?php

namespace App\Policies;

use App\Models\ReportingPeriod;
use App\Models\User;

class ReportingPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isSchoolRepresentative() && $user->hasPermission('reporting-periods.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isSchoolRepresentative() && $user->hasPermission('reporting-periods.manage');
    }

    public function update(User $user, ReportingPeriod $period): bool
    {
        return ! $user->isSchoolRepresentative() && $user->hasPermission('reporting-periods.manage');
    }
}
