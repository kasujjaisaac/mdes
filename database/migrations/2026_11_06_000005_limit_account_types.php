<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $admin = DB::table('roles')->where('slug', 'system-super-administrator')->value('id');
            $unassigned = DB::table('users')->whereNull('school_id')->when($admin, fn ($q) => $q->whereNotIn('id', DB::table('role_user')->where('role_id', $admin)->select('user_id')))->exists();
            if ($unassigned) {
                throw new RuntimeException('Some non-admin accounts have no assigned school. Assign each to its school before simplifying account types. No accounts were changed.');
            }
            $admin = $admin ?: DB::table('roles')->insertGetId(['slug' => 'system-super-administrator', 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('roles')->where('id', $admin)->update(['name' => 'Admin']);
            $head = DB::table('roles')->where('slug', 'headteacher')->value('id') ?: DB::table('roles')->insertGetId(['slug' => 'headteacher', 'name' => 'School Headteacher', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('roles')->where('id', $head)->update(['name' => 'School Headteacher']);
            DB::table('permission_role')->where('role_id', $head)->delete();
            foreach (DB::table('permissions')->whereIn('slug', ['dashboard.view', 'schools.view.assigned', 'returns.view', 'returns.manage.assigned', 'reporting-periods.view', 'documents.view'])->pluck('id') as $permission) {
                DB::table('permission_role')->insert(['role_id' => $head, 'permission_id' => $permission]);
            }
            foreach (DB::table('users')->get() as $user) {
                $isAdmin = DB::table('role_user')->where('user_id', $user->id)->where('role_id', $admin)->exists();
                DB::table('role_user')->where('user_id', $user->id)->delete();
                DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $isAdmin ? $admin : $head]);
                DB::table('users')->where('id', $user->id)->update(['designation' => $isAdmin ? 'Admin' : 'School Headteacher', 'remember_token' => Str::random(60)]);
                if (config('session.driver') === 'database') {
                    DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
            }
            DB::table('roles')->whereNotIn('id', [$admin, $head])->delete();
        });
    }

    public function down(): void
    { /* Preserve explicit account assignments. */
    }
};
