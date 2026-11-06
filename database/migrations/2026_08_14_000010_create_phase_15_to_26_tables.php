<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->json('checklist_items');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class, 'inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('purpose');
            $table->date('planned_date');
            $table->string('status')->default('planned');
            $table->json('checklist_responses')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status', 'planned_date']);
        });

        Schema::create('inspection_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('severity');
            $table->text('finding');
            $table->text('recommendation')->nullable();
            $table->string('responsible_person')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
            $table->index(['severity', 'status']);
        });

        Schema::create('improvement_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->text('problem_statement');
            $table->string('domain');
            $table->string('baseline')->nullable();
            $table->string('target')->nullable();
            $table->string('success_indicator')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::create('improvement_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('improvement_plan_id')->constrained()->cascadeOnDelete();
            $table->text('activity');
            $table->string('responsible_person')->nullable();
            $table->date('due_date')->nullable();
            $table->string('expected_output')->nullable();
            $table->decimal('estimated_budget', 14, 2)->default(0);
            $table->string('support_required')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('percent_complete')->default(0);
            $table->text('progress_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('development_needs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->string('category');
            $table->text('description');
            $table->text('justification')->nullable();
            $table->string('urgency')->default('normal');
            $table->unsignedInteger('beneficiaries')->default(0);
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->decimal('school_contribution', 14, 2)->default(0);
            $table->string('review_status')->default('submitted');
            $table->string('funding_status')->default('unfunded');
            $table->string('priority')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'funding_status', 'review_status']);
        });

        Schema::create('development_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('development_need_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('approved_budget', 14, 2)->default(0);
            $table->string('funder')->nullable();
            $table->string('implementer')->nullable();
            $table->date('start_date')->nullable();
            $table->date('planned_completion_date')->nullable();
            $table->string('status')->default('planned');
            $table->unsignedTinyInteger('percent_complete')->default(0);
            $table->timestamps();
        });

        Schema::create('project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('development_project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->text('evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('budget_year');
            $table->json('budget_categories')->nullable();
            $table->json('income_categories')->nullable();
            $table->json('expenditure_categories')->nullable();
            $table->decimal('approved_budget_total', 14, 2)->default(0);
            $table->decimal('income_total', 14, 2)->default(0);
            $table->decimal('expenditure_total', 14, 2)->default(0);
            $table->decimal('variance', 14, 2)->default(0);
            $table->text('variance_explanation')->nullable();
            $table->boolean('audited_accounts_available')->default(false);
            $table->date('audit_date')->nullable();
            $table->string('audit_status')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'budget_year']);
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('target_scope')->default('all_schools');
            $table->json('target_filters')->nullable();
            $table->boolean('requires_acknowledgement')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('severity')->default('info');
            $table->text('message');
            $table->string('status')->default('open');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('helpdesk_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class, 'opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category');
            $table->string('priority')->default('normal');
            $table->text('description');
            $table->string('status')->default('open');
            $table->timestamps();
            $table->index(['status', 'priority']);
        });

        Schema::create('helpdesk_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('helpdesk_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class, 'generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type');
            $table->string('format');
            $table->json('filters')->nullable();
            $table->string('status')->default('generated');
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        Schema::create('acceptance_test_results', function (Blueprint $table): void {
            $table->id();
            $table->string('phase');
            $table->string('scenario');
            $table->text('expected_result');
            $table->text('actual_result')->nullable();
            $table->string('status')->default('pending');
            $table->string('severity')->default('normal');
            $table->timestamps();
        });

        Schema::create('production_readiness_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('classification');
            $table->json('implemented_modules');
            $table->json('security_notes')->nullable();
            $table->json('workflow_notes')->nullable();
            $table->json('outstanding_decisions')->nullable();
            $table->json('technical_debt')->nullable();
            $table->foreignIdFor(User::class, 'reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_readiness_reviews');
        Schema::dropIfExists('acceptance_test_results');
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('helpdesk_messages');
        Schema::dropIfExists('helpdesk_tickets');
        Schema::dropIfExists('notification_records');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('financial_statistics');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('development_projects');
        Schema::dropIfExists('development_needs');
        Schema::dropIfExists('improvement_actions');
        Schema::dropIfExists('improvement_plans');
        Schema::dropIfExists('inspection_findings');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('inspection_templates');
    }
};
