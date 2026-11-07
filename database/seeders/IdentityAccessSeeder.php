<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class IdentityAccessSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private array $permissions = [
        'users.view' => 'View users',
        'users.manage' => 'Manage users',
        'roles.view' => 'View roles and permissions',
        'roles.manage' => 'Manage roles and permissions',
        'administration.access' => 'Access administration',
        'dashboard.view' => 'View dashboard',
        'diocesan-structure.view' => 'View diocesan structure',
        'diocesan-structure.manage' => 'Manage diocesan structure',
        'schools.view.all' => 'View all schools',
        'schools.view.assigned' => 'View own registered school',
        'schools.manage' => 'Manage school registry',
        'reporting-periods.view' => 'View reporting periods',
        'reporting-periods.manage' => 'Manage reporting periods',
        'returns.view' => 'View school returns',
        'returns.manage.all' => 'Manage all school returns',
        'returns.manage.assigned' => 'Manage own school returns',
        'returns.review' => 'Return submitted school data for correction',
        'documents.view' => 'View protected documents',
        'documents.manage' => 'Manage protected documents',
        'inspections.view' => 'View inspections',
        'inspections.manage' => 'Manage inspections',
        'improvement-plans.view' => 'View improvement plans',
        'improvement-plans.manage' => 'Manage improvement plans',
        'development.view' => 'View development needs and projects',
        'development.manage' => 'Manage development needs and projects',
        'financial-statistics.view' => 'View financial statistics',
        'financial-statistics.manage' => 'Manage financial statistics',
        'communications.view' => 'View communications',
        'communications.manage' => 'Manage communications',
        'helpdesk.view' => 'View helpdesk',
        'reports.view' => 'View reports and analytics',
        'reports.export' => 'Export reports',
        'audit.view' => 'View audit trail',
        'reference-data.view' => 'View reference data',
        'settings.view' => 'View system settings',
        'logs.view' => 'View system logs',
    ];

    /**
     * @var array<string, string>
     */
    private array $roles = [
        'system-super-administrator' => 'Admin',
        'headteacher' => 'School Headteacher',
    ];

    public function run(): void
    {
        $permissions = collect($this->permissions)
            ->mapWithKeys(fn (string $name, string $slug) => [
                $slug => Permission::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'description' => $name],
                ),
            ]);

        foreach ($this->roles as $slug => $name) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $name],
            );

            if ($slug === Role::SUPER_ADMINISTRATOR) {
                $role->permissions()->sync($permissions->pluck('id')->all());
            } else {
                $role->permissions()->sync($permissions->only(Role::HEADTEACHER_PERMISSIONS)->pluck('id')->all());
            }
        }
    }
}
