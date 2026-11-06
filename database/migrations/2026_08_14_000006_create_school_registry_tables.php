<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diocese_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parish_id')->constrained()->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_county_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('education_cluster_id')->nullable()->constrained()->nullOnDelete();
            $table->string('official_name');
            $table->string('diocesan_code')->unique();
            $table->string('emis_number')->nullable()->unique();
            $table->string('ministry_registration_number')->nullable()->unique();
            $table->string('examination_centre_number')->nullable()->unique();
            $table->string('former_name')->nullable();
            $table->string('school_level');
            $table->string('ownership');
            $table->string('funding_classification')->nullable();
            $table->string('gender_classification')->nullable();
            $table->string('boarding_status')->nullable();
            $table->string('operational_status')->default('active');
            $table->string('village')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('profile_verified')->default(false);
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['parish_id', 'operational_status']);
            $table->index(['district_id', 'school_level']);
        });

        Schema::create('school_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('postal_address')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('school_leadership_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('position');
            $table->string('person_name');
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->index(['school_id', 'position', 'is_current']);
        });

        Schema::create('school_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->foreignIdFor(User::class, 'responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('school_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('value');
            $table->timestamps();
            $table->unique(['type', 'value']);
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('school_identifiers');
        Schema::dropIfExists('school_status_histories');
        Schema::dropIfExists('school_leadership_histories');
        Schema::dropIfExists('school_contacts');
        Schema::dropIfExists('schools');
    }
};
