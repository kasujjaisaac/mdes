<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('school_returns', function (Blueprint $table) { $table->json('statistical_form')->nullable(); }); }
    public function down(): void { Schema::table('school_returns', function (Blueprint $table) { $table->dropColumn('statistical_form'); }); }
};
