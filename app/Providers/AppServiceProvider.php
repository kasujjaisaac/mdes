<?php

namespace App\Providers;

use App\Models\ManagedDocument;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Policies\ManagedDocumentPolicy;
use App\Policies\ParishPolicy;
use App\Policies\ReportingPeriodPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\SchoolReturnPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if ($ability === 'update' && ($arguments[0] ?? null) instanceof SchoolReturn) {
                return null;
            }

            return $user->isAdministrator() ? true : null;
        });

        Gate::define('dashboard.view', fn (User $user): bool => $user->hasPermission('dashboard.view'));
        Gate::define('administration.access', fn (User $user): bool => $user->hasPermission('administration.access'));
        Gate::define('users.view', fn (User $user): bool => $user->hasPermission('users.view'));
        Gate::define('users.manage', fn (User $user): bool => $user->hasPermission('users.manage'));
        Gate::define('roles.view', fn (User $user): bool => $user->hasPermission('roles.view'));
        Gate::define('roles.manage', fn (User $user): bool => $user->hasPermission('roles.manage'));
        Gate::define('diocesan-structure.view', fn (User $user): bool => $user->hasPermission('diocesan-structure.view'));
        Gate::define('diocesan-structure.manage', fn (User $user): bool => $user->hasPermission('diocesan-structure.manage'));
        Gate::define('schools.view', fn (User $user): bool => $user->hasPermission('schools.view.all') || $user->hasPermission('schools.view.assigned'));
        Gate::define('schools.manage', fn (User $user): bool => $user->hasPermission('schools.manage'));
        Gate::define('reporting-periods.view', fn (User $user): bool => $user->hasPermission('reporting-periods.view'));
        Gate::define('reporting-periods.manage', fn (User $user): bool => $user->hasPermission('reporting-periods.manage'));
        Gate::define('returns.view', fn (User $user): bool => $user->hasPermission('returns.view'));
        Gate::define('documents.view', fn (User $user): bool => $user->hasPermission('documents.view'));
        Gate::define('documents.manage', fn (User $user): bool => $user->hasPermission('documents.manage'));
        Gate::define('inspections.view', fn (User $user): bool => $user->hasPermission('inspections.view'));
        Gate::define('inspections.manage', fn (User $user): bool => $user->hasPermission('inspections.manage'));
        Gate::define('improvement-plans.view', fn (User $user): bool => $user->hasPermission('improvement-plans.view'));
        Gate::define('improvement-plans.manage', fn (User $user): bool => $user->hasPermission('improvement-plans.manage'));
        Gate::define('development.view', fn (User $user): bool => $user->hasPermission('development.view'));
        Gate::define('development.manage', fn (User $user): bool => $user->hasPermission('development.manage'));
        Gate::define('financial-statistics.view', fn (User $user): bool => $user->hasPermission('financial-statistics.view'));
        Gate::define('financial-statistics.manage', fn (User $user): bool => $user->hasPermission('financial-statistics.manage'));
        Gate::define('communications.view', fn (User $user): bool => $user->hasPermission('communications.view'));
        Gate::define('communications.manage', fn (User $user): bool => $user->hasPermission('communications.manage'));
        Gate::define('helpdesk.view', fn (User $user): bool => $user->hasPermission('helpdesk.view'));
        Gate::define('reports.view', fn (User $user): bool => $user->hasPermission('reports.view'));
        Gate::define('reports.export', fn (User $user): bool => $user->hasPermission('reports.export'));
        Gate::define('audit.view', fn (User $user): bool => $user->hasPermission('audit.view'));
        Gate::define('reference-data.view', fn (User $user): bool => $user->hasPermission('reference-data.view'));
        Gate::define('settings.view', fn (User $user): bool => $user->hasPermission('settings.view'));
        Gate::define('logs.view', fn (User $user): bool => $user->hasPermission('logs.view'));

        Gate::policy(ManagedDocument::class, ManagedDocumentPolicy::class);
        Gate::policy(Parish::class, ParishPolicy::class);
        Gate::policy(School::class, SchoolPolicy::class);
        Gate::policy(ReportingPeriod::class, ReportingPeriodPolicy::class);
        Gate::policy(SchoolReturn::class, SchoolReturnPolicy::class);
    }
}
