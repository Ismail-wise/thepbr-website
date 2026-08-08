<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_dynamics_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')
                ->constrained('partnership_workspaces')
                ->cascadeOnDelete();

            $table->string('report_version', 20)->default('v1');

            // waiting = not all required participants have completed assessment
            // ready = report successfully generated
            $table->string('status', 20)->default('waiting');

            /*
             * Snapshot of every participant used to generate this report.
             * Supports 2, 3, 4+ partners without changing the schema.
             *
             * Example:
             * [
             *   {
             *     "user_id": 2,
             *     "assessment_id": 10,
             *     "primary_profile": "Visionary"
             *   }
             * ]
             */
            $table->json('participants')->nullable();

            // Summary counts and overall alignment information.
            $table->json('alignment_summary')->nullable();

            // Areas where partners are naturally strong together.
            $table->json('shared_strengths')->nullable();

            // Areas where one partner's natural strength can support another.
            $table->json('complementary_areas')->nullable();

            // Large differences that should be discussed.
            $table->json('important_differences')->nullable();

            // Areas where all partners may naturally give less attention.
            $table->json('shared_blind_spots')->nullable();

            // Suggested responsibility allocation.
            $table->json('role_suggestions')->nullable();

            // Suggested rules for approvals and important decisions.
            $table->json('decision_recommendations')->nullable();

            // Priority discussion points before/during PBR Chapters.
            $table->json('discussion_priorities')->nullable();

            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->index(
                ['workspace_id', 'status', 'generated_at'],
                'pdr_workspace_status_generated_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_dynamics_reports');
    }
};
