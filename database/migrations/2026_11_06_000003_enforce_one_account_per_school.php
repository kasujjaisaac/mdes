<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->whereNotNull('school_id')->groupBy('school_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Some schools have multiple accounts. Resolve these assignments before applying the one-account-per-school rule. No accounts have been removed.');
        }
        $schools = DB::table('schools')->whereNotNull('ministry_registration_number')->get(['id', 'ministry_registration_number']);
        $normalized = $schools->mapWithKeys(fn ($school) => [$school->id => mb_strtoupper(preg_replace('/\s+/u', '', trim($school->ministry_registration_number)))]);
        if ($normalized->filter()->duplicates()->isNotEmpty()) {
            throw new RuntimeException('Duplicate MOE Reg Nos exist after normalizing spaces and letter case. Correct these before migrating. No school records have been changed.');
        }
        Schema::table('users', fn (Blueprint $table) => $table->unique('school_id', 'users_one_account_per_school'));
        foreach ($normalized as $id => $number) {
            DB::table('schools')->where('id', $id)->update(['ministry_registration_number' => $number ?: null]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_one_account_per_school'));
    }
};
