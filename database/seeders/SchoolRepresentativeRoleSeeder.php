<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

// Kept as an entry point for existing installation scripts.
class SchoolRepresentativeRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::updateOrCreate(['slug' => 'headteacher'], ['name' => 'School Headteacher', 'description' => 'Headteacher account for one assigned school.']);
        $role->permissions()->sync(Permission::whereIn('slug', ['dashboard.view', 'schools.view.assigned', 'returns.view', 'returns.manage.assigned', 'reporting-periods.view', 'documents.view'])->pluck('id'));
    }
}
