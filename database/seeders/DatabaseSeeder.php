<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(IdentityAccessSeeder::class);
        $this->call(SchoolRepresentativeRoleSeeder::class);
        $this->call(DiocesanStructureSeeder::class);
        $this->call(PhaseNineToFourteenSeeder::class);
        $this->call(PhaseFifteenToTwentySixSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $admin = User::query()->firstOrCreate([
            'email' => 'admin@mdsiss.test',
        ], [
            'name' => 'MDSISS Administrator',
            'telephone' => '+256700000000',
            'designation' => 'Admin',
            'status' => User::STATUS_ACTIVE,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $adminRole = Role::query()->where('slug', Role::SUPER_ADMINISTRATOR)->first();

        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
