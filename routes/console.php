<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mdsiss:security-audit', function () {
    $process = new Process(['composer', 'audit', '--locked', '--format=json'], base_path());
    $process->setTimeout(180);
    $process->run();
    $report = json_decode($process->getOutput(), true);
    $clean = $process->isSuccessful() && is_array($report) && empty($report['advisories']) && empty($report['abandoned']);
    Storage::disk('local')->put('security/dependency-audit.json', json_encode(['clean' => $clean, 'checked_at' => time(), 'lock_hash' => hash_file('sha256', base_path('composer.lock')), 'report' => $report], JSON_PRETTY_PRINT));
    $this->line($clean ? 'Dependency audit clean.' : 'Dependency audit failed or found advisories. Review composer audit output.');

    return $clean ? 0 : 1;
})->purpose('Record a fresh dependency audit tied to the current Composer lockfile');

Artisan::command('mdsiss:create-admin', function () {
    $name = $this->ask('Administrator name');
    $email = $this->ask('Administrator email');
    $password = $this->secret('Password (at least 12 characters)');
    $confirmation = $this->secret('Confirm password');
    Validator::make(compact('name', 'email', 'password') + ['password_confirmation' => $confirmation], ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12|confirmed'])->validate();
    DB::transaction(function () use ($name, $email, $password) {
        $role = Role::where('slug', Role::SUPER_ADMINISTRATOR)->firstOrFail();
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'status' => 'active']);
        $user->roles()->attach($role);
    });
    $this->info('Administrator created.');
})->purpose('Provision a named administrator without default credentials');
