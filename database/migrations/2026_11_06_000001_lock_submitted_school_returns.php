<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_returns')->whereIn('status', ['submitted', 'certified', 'approved'])->update([
            'status' => 'locked',
            'locked_at' => DB::raw('COALESCE(locked_at, submitted_at, updated_at)'),
        ]);
    }

    public function down(): void
    {
        // Keep submitted data locked on rollback; historical approval timestamps remain intact.
    }
};
