<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'workspace_v2_review_cases',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id')
                    ->constrained('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'target_canonical_revision_id'
                )
                    ->constrained(
                        'workspace_v2_canonical_revisions'
                    )
                    ->restrictOnDelete();

                $table->string(
                    'target_domain_key',
                    80
                );

                $table->string(
                    'target_component_key',
                    120
                );

                $table->unsignedInteger(
                    'target_revision'
                );

                $table->string(
                    'producer_domain_key',
                    80
                );

                $table->string(
                    'producer_component_key',
                    120
                );

                $table->string(
                    'trigger_type',
                    32
                );

                $table->json(
                    'trigger_context'
                )->nullable();

                $table->string(
                    'status',
                    24
                )->index();

                $table->string(
                    'resolution',
                    32
                )->nullable();

                $table->text(
                    'resolution_note'
                )->nullable();

                $table->foreignId(
                    'opened_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId(
                    'resolved_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('opened_at');

                $table->timestamp(
                    'resolved_at'
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'workspace_id',
                        'target_domain_key',
                        'target_component_key',
                        'status',
                    ],
                    'ws_v2_review_target_status_idx'
                );

                $table->index(
                    [
                        'target_canonical_revision_id',
                        'status',
                    ],
                    'ws_v2_review_revision_status_idx'
                );
            }
        );

        Schema::create(
            'workspace_v2_review_audit_events',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id')
                    ->constrained('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->foreignId('review_case_id')
                    ->constrained(
                        'workspace_v2_review_cases'
                    )
                    ->restrictOnDelete();

                $table->string(
                    'event_type',
                    32
                );

                $table->foreignId(
                    'actor_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamp(
                    'occurred_at'
                );

                $table->index(
                    [
                        'review_case_id',
                        'occurred_at',
                    ],
                    'ws_v2_review_audit_case_idx'
                );

                $table->index(
                    [
                        'workspace_id',
                        'event_type',
                    ],
                    'ws_v2_review_audit_event_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'workspace_v2_review_audit_events'
        );

        Schema::dropIfExists(
            'workspace_v2_review_cases'
        );
    }
};
