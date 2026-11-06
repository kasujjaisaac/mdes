<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_exports', function (Blueprint $table) {
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->text('failure_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('report_exports', fn (Blueprint $table) => $table->dropColumn(['disk', 'path', 'failure_reason']));
    }
};
