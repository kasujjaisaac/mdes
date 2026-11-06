<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('telephone')->nullable()->after('email');
            $table->string('designation')->nullable()->after('telephone');
            $table->string('status')->default('active')->after('designation');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['telephone', 'designation', 'status', 'last_login_at']);
        });
    }
};
