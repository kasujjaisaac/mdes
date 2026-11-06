<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dioceses', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sub_counties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['district_id', 'name']);
        });

        Schema::create('education_clusters', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('parishes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diocese_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['diocese_id', 'name']);
        });

        Schema::create('jurisdiction_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignId('parish_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('education_cluster_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assignment_type');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'assignment_type', 'is_active'], 'ja_user_type_active_idx');
            $table->index(['parish_id', 'assignment_type', 'is_active'], 'ja_parish_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisdiction_assignments');
        Schema::dropIfExists('parishes');
        Schema::dropIfExists('education_clusters');
        Schema::dropIfExists('sub_counties');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('dioceses');
    }
};
