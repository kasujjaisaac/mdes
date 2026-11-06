<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_national')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('grading_scales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examination_type_id')->constrained()->cascadeOnDelete();
            $table->string('grade');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_passing')->default(true);
            $table->timestamps();
            $table->unique(['examination_type_id', 'grade']);
        });

        Schema::create('academic_performance_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_type_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('registered_candidates')->default(0);
            $table->unsignedInteger('candidates_sat')->default(0);
            $table->unsignedInteger('absentees')->default(0);
            $table->unsignedInteger('male_candidates')->default(0);
            $table->unsignedInteger('female_candidates')->default(0);
            $table->json('grade_distribution')->nullable();
            $table->decimal('pass_rate', 5, 2)->default(0);
            $table->text('weaknesses')->nullable();
            $table->text('intervention_notes')->nullable();
            $table->timestamps();
            $table->unique(['school_return_id', 'examination_type_id'], 'return_exam_unique');
        });

        Schema::create('infrastructure_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->string('facility_type');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('functional')->default(0);
            $table->unsignedInteger('damaged')->default(0);
            $table->unsignedInteger('required')->default(0);
            $table->string('condition')->default('fair');
            $table->string('priority')->default('normal');
            $table->string('water_source')->nullable();
            $table->string('water_reliability')->nullable();
            $table->boolean('has_electricity')->default(false);
            $table->boolean('has_internet')->default(false);
            $table->unsignedInteger('computers')->default(0);
            $table->boolean('fire_safety_available')->default(false);
            $table->boolean('accessibility_available')->default(false);
            $table->boolean('security_fenced')->default(false);
            $table->boolean('high_risk_condition')->default(false);
            $table->unsignedInteger('gap')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['school_return_id', 'facility_type']);
        });

        Schema::create('catholic_identity_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->boolean('has_chaplaincy')->default(false);
            $table->string('pastoral_contact')->nullable();
            $table->string('mass_frequency')->nullable();
            $table->unsignedInteger('retreats_held')->default(0);
            $table->boolean('catechesis_available')->default(false);
            $table->boolean('values_education_available')->default(false);
            $table->boolean('safeguarding_structure_available')->default(false);
            $table->boolean('counselling_structure_available')->default(false);
            $table->unsignedInteger('parish_school_engagements')->default(0);
            $table->text('pastoral_programmes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('school_return_id');
        });

        Schema::create('governance_compliance_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->boolean('board_active')->default(false);
            $table->date('last_board_meeting_date')->nullable();
            $table->unsignedInteger('board_meetings_held')->default(0);
            $table->boolean('pta_active')->default(false);
            $table->boolean('child_protection_policy_available')->default(false);
            $table->boolean('financial_policy_available')->default(false);
            $table->boolean('risk_register_available')->default(false);
            $table->boolean('mandatory_reports_submitted')->default(false);
            $table->unsignedInteger('open_compliance_issues')->default(0);
            $table->text('leadership_notes')->nullable();
            $table->text('compliance_notes')->nullable();
            $table->timestamps();
            $table->unique('school_return_id');
        });

        Schema::create('return_certifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'certified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('declaration');
            $table->json('snapshot');
            $table->timestamp('certified_at');
            $table->timestamps();
        });

        Schema::create('data_quality_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->string('section')->nullable();
            $table->string('severity');
            $table->string('rule');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['school_return_id', 'severity']);
        });

        Schema::create('document_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('managed_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('sensitivity')->default('internal');
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'expiry_date']);
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('managed_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('reason')->nullable();
            $table->boolean('is_current')->default(true);
            $table->foreignIdFor(User::class, 'uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->unique(['managed_document_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('managed_documents');
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('data_quality_findings');
        Schema::dropIfExists('return_certifications');
        Schema::dropIfExists('governance_compliance_statistics');
        Schema::dropIfExists('catholic_identity_statistics');
        Schema::dropIfExists('infrastructure_statistics');
        Schema::dropIfExists('academic_performance_statistics');
        Schema::dropIfExists('grading_scales');
        Schema::dropIfExists('examination_types');
    }
};
