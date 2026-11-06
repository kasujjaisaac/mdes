<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', fn (Blueprint $table) => $table->uuid('publication_key')->nullable()->unique());
        Schema::table('notification_records', fn (Blueprint $table) => $table->foreignId('announcement_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('notification_records', fn (Blueprint $table) => $table->dropConstrainedForeignId('announcement_id'));
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropUnique(['publication_key']);
            $table->dropColumn('publication_key');
        });
    }
};
