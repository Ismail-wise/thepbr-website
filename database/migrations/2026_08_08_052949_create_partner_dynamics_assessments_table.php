<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_dynamics_assessments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Allows us to improve the assessment later without breaking old results.
            $table->string('assessment_version', 20)->default('v1');

            // draft = started but not finished
            // completed = result available
            $table->string('status', 20)->default('draft');

            // Q1-Q40 answers.
            $table->json('answers')->nullable();

            // Eight normalized scores (0-100).
            $table->json('dimension_scores')->nullable();

            // Eight profile scores (0-100).
            $table->json('profile_scores')->nullable();

            $table->string('primary_profile', 50)->nullable();
            $table->decimal('primary_score', 5, 2)->nullable();

            $table->string('secondary_profile', 50)->nullable();
            $table->decimal('secondary_score', 5, 2)->nullable();

            // True when Primary and Secondary are within the blended threshold.
            $table->boolean('is_blended')->default(false);

            // strong / moderate
            $table->string('result_confidence', 20)->nullable();

            // Internal consistency information.
            $table->json('consistency_data')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['user_id', 'status', 'completed_at'],
                'pda_user_status_completed_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_dynamics_assessments');
    }
};
