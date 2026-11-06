<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('username', 80)->nullable()->unique());
        // Reserve school MOE numbers first, then allocate distinct usernames to other accounts.
        foreach (DB::table('users')->orderByRaw('CASE WHEN school_id IS NULL THEN 1 ELSE 0 END')->orderBy('id')->get() as $user) {
            $moe = $user->school_id ? DB::table('schools')->where('id', $user->school_id)->value('ministry_registration_number') : null;
            $base = mb_strtoupper(preg_replace('/\s+/u', '', trim($moe ?: strstr($user->email, '@', true))));
            $base = mb_substr($base ?: 'USER', 0, $moe ? 80 : 76);
            $candidate = $base;
            $suffix = 2;
            while (DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = $base.'-'.$suffix++;
            }
            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('username'));
    }
};
