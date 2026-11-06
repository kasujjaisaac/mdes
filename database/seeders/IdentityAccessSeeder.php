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
        'schools.view.assigned' => 'View assigned parish schools',
        'schools.manage' => 'Manage school registry',
        'reporting-periods.view' => 'View reporting periods',
        'reporting-periods.manage' => 'Manage reporting periods',
        'returns.view' => 'View school returns',
        'returns.manage.all' => 'Manage all school returns',
        'returns.manage.assigned' => 'Manage assigned school returns',
        'returns.review' => 'Review and approve returns',
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
        'system-super-administrator' => 'System Super Administrator',
        'diocesan-education-secretary' => 'Diocesan Education Secretary',
        'diocesan-education-officer' => 'Diocesan Education Officer',
        'parish-education-coordinator' => 'Parish Education Coordinator',
        'inspector' => 'Inspector',
        'headteacher' => 'Headteacher',
        'school-data-officer' => 'School Data Officer',
        'auditor' => 'Auditor',
        'board-leadership-viewer' => 'Board/Leadership Viewer',
        'technical-administrator' => 'Technical Administrator',
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
            } elseif (in_array($slug, ['diocesan-education-secretary', 'diocesan-education-officer'], true)) {
                $role->permissions()->sync($permissions->only([
                    'dashboard.view',
                    'diocesan-structure.view',
                    'diocesan-structure.manage',
                    'schools.view.all',
                    'schools.manage',
                    'reporting-periods.view',
                    'reporting-periods.manage',
                    'returns.view',
                    'returns.manage.all',
                    'returns.review',
                    'documents.view',
                    'documents.manage',
                    'inspections.view',
                    'inspections.manage',
                    'improvement-plans.view',
                    'improvement-plans.manage',
                    'development.view',
                    'development.manage',
                    'financial-statistics.view',
                    'financial-statistics.manage',
                    'communications.view',
                    'communications.manage',
                    'helpdesk.view',
                    'reports.view',
                    'reports.export',
                    'audit.view',
                    'reference-data.view',
                    'settings.view',
                    'logs.view',
                ])->pluck('id')->all());
            } elseif ($slug === 'parish-education-coordinator') {
                $role->permissions()->sync($permissions->only([
                    'dashboard.view',
                    'diocesan-structure.view',
                    'schools.view.assigned',
                    'reporting-periods.view',
                    'returns.view',
                    'returns.manage.assigned',
                    'returns.review',
                    'documents.view',
                ])->pluck('id')->all());
            } elseif (in_array($slug, ['headteacher', 'school-data-officer'], true)) {
                $role->permissions()->sync($permissions->only([
                    'dashboard.view',
                    'schools.view.assigned',
                    'reporting-periods.view',
                    'returns.view',
                    'returns.manage.assigned',
                    'documents.view',
                ])->pluck('id')->all());
            }
        }
    }
}
