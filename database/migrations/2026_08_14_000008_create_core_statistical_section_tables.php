<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolment_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->string('class_level');
            $table->unsignedInteger('boys')->default(0);
            $table->unsignedInteger('girls')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('day_scholars')->default(0);
            $table->unsignedInteger('boarders')->default(0);
            $table->unsignedInteger('repeaters')->default(0);
            $table->unsignedInteger('transfers_in')->default(0);
            $table->unsignedInteger('transfers_out')->default(0);
            $table->unsignedInteger('dropouts')->default(0);
            $table->unsignedInteger('re_entries')->default(0);
            $table->unsignedInteger('completions')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['school_return_id', 'class_level']);
        });

        Schema::create('attendance_welfare_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('enrolled_learners')->default(0);
            $table->unsignedInteger('expected_attendance')->default(0);
            $table->unsignedInteger('aggregate_attendance')->default(0);
            $table->decimal('attendance_rate', 5, 2)->default(0);
            $table->unsignedInteger('retained_learners')->default(0);
            $table->unsignedInteger('dropouts')->default(0);
            $table->unsignedInteger('re_entries')->default(0);
            $table->unsignedInteger('transitioned_learners')->default(0);
            $table->unsignedInteger('welfare_cases_aggregated')->default(0);
            $table->text('trend_explanation')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('male_teaching_staff')->default(0);
            $table->unsignedInteger('female_teaching_staff')->default(0);
            $table->unsignedInteger('male_non_teaching_staff')->default(0);
            $table->unsignedInteger('female_non_teaching_staff')->default(0);
            $table->unsignedInteger('qualified_teachers')->default(0);
            $table->unsignedInteger('unqualified_teachers')->default(0);
            $table->unsignedInteger('government_payroll_staff')->default(0);
            $table->unsignedInteger('private_payroll_staff')->default(0);
            $table->unsignedInteger('approved_establishment')->default(0);
            $table->unsignedInteger('filled_positions')->default(0);
            $table->unsignedInteger('vacancies')->default(0);
            $table->decimal('vacancy_rate', 5, 2)->default(0);
            $table->decimal('pupil_teacher_ratio', 8, 2)->nullable();
            $table->text('training_needs')->nullable();
            $table->text('housing_gaps')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_statistics');
        Schema::dropIfExists('attendance_welfare_statistics');
        Schema::dropIfExists('enrolment_statistics');
    }
};
