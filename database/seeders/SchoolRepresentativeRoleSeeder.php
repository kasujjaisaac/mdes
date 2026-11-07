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
        $role->permissions()->sync(Permission::whereIn('slug', Role::HEADTEACHER_PERMISSIONS)->pluck('id'));
    }
}
