<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_records', fn (Blueprint $table) => $table->string('notification_key', 190)->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('notification_records', function (Blueprint $table): void {
            $table->dropUnique(['notification_key']);
            $table->dropColumn('notification_key');
        });
    }
};
