<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->unsignedSmallInteger('year');
            $table->string('term')->nullable();
            $table->text('instructions')->nullable();
            $table->date('opening_date');
            $table->date('closing_date');
            $table->date('extension_date')->nullable();
            $table->date('lock_date')->nullable();
            $table->string('status')->default('draft');
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'year', 'status']);
        });

        Schema::create('reporting_period_school', function (Blueprint $table): void {
            $table->foreignId('reporting_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->primary(['reporting_period_id', 'school_id'], 'rp_school_primary');
        });

        Schema::create('school_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->foreignIdFor(User::class, 'started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignIdFor(User::class, 'certified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('certified_at')->nullable();
            $table->foreignIdFor(User::class, 'approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('correction_reason')->nullable();
            $table->timestamps();
            $table->unique(['reporting_period_id', 'school_id'], 'school_return_unique');
            $table->index(['status', 'reporting_period_id']);
        });

        Schema::create('return_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->string('status')->default('not_started');
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['school_return_id', 'section']);
        });

        Schema::create('return_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome');
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_reviews');
        Schema::dropIfExists('return_sections');
        Schema::dropIfExists('school_returns');
        Schema::dropIfExists('reporting_period_school');
        Schema::dropIfExists('reporting_periods');
    }
};
