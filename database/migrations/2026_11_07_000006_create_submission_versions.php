<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_return_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('payload');
            $table->string('content_hash', 64);
            $table->string('review_status');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('correction_reason')->nullable();
            $table->boolean('legacy_import')->default(false);
            $table->timestamps();
            $table->unique(['school_return_id', 'version']);
        });
        // Preserve existing locked/approved data; never invent the earlier state of a corrected draft.
        DB::table('school_returns')->whereNotNull('submitted_at')->whereNotNull('statistical_form')
            ->whereIn('status', ['submitted', 'certified', 'approved', 'locked'])->orderBy('id')->chunkById(100, function ($returns) {
                foreach ($returns as $return) {
                    $school = (array) DB::table('schools')->find($return->school_id);
                    $period = (array) DB::table('reporting_periods')->find($return->reporting_period_id);
                    $payload = ['form' => json_decode($return->statistical_form, true), 'profile' => json_decode($return->profile_snapshot ?? 'null', true), 'school' => $school, 'period' => $period];
                    DB::table('submission_versions')->insert(['school_return_id' => $return->id, 'version' => 1, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                        'review_status' => $return->status, 'submitted_by' => $return->submitted_by, 'submitted_at' => $return->submitted_at, 'reviewed_at' => $return->approved_at, 'reviewed_by' => $return->approved_by,
                        'legacy_import' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_versions');
    }
};
