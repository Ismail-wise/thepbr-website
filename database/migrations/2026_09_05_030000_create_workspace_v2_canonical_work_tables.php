<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'workspace_v2_canonical_work_artifacts',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id')
                    ->constrained('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->string(
                    'target_domain_key',
                    80
                );

                $table->string(
                    'target_component_key',
                    120
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
                    'state',
                    32
                );

                $table->json('payload');

                $table->unsignedInteger(
                    'source_generation'
                )->default(1);

                $table->foreignId(
                    'created_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId(
                    'submitted_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'submitted_at'
                )->nullable();

                $table->foreignId(
                    'amendment_review_case_id'
                )
                    ->nullable()
                    ->constrained(
                        'workspace_v2_review_cases'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'amendment_target_canonical_revision_id'
                )
                    ->nullable()
                    ->constrained(
                        'workspace_v2_canonical_revisions'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'published_canonical_revision_id'
                )
                    ->nullable()
                    ->constrained(
                        'workspace_v2_canonical_revisions'
                    )
                    ->restrictOnDelete();

                $table->timestamp(
                    'consumed_at'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'workspace_id',
                    'target_domain_key',
                    'target_component_key',
                    'state',
                ]);

                $table->index([
                    'workspace_id',
                    'producer_domain_key',
                    'producer_component_key',
                ]);

                $table->index(
                    'amendment_review_case_id'
                );

                $table->index(
                    'amendment_target_canonical_revision_id'
                );

                $table->index(
                    'published_canonical_revision_id'
                );
            }
        );

        Schema::create(
            'workspace_v2_canonical_work_sources',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id')
                    ->constrained('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'work_artifact_id'
                )
                    ->constrained(
                        'workspace_v2_canonical_work_artifacts'
                    )
                    ->restrictOnDelete();

                $table->unsignedInteger(
                    'source_generation'
                );

                $table->foreignId(
                    'canonical_revision_id'
                )
                    ->constrained(
                        'workspace_v2_canonical_revisions'
                    )
                    ->restrictOnDelete();

                $table->string(
                    'source_domain_key',
                    80
                );

                $table->string(
                    'source_component_key',
                    120
                );

                $table->unsignedInteger(
                    'source_revision'
                );

                $table->timestamp(
                    'pinned_at'
                );

                $table->unique([
                    'work_artifact_id',
                    'source_generation',
                    'canonical_revision_id',
                ]);

                $table->index([
                    'workspace_id',
                    'canonical_revision_id',
                ]);

                $table->index([
                    'work_artifact_id',
                    'source_generation',
                ]);

                $table->index([
                    'work_artifact_id',
                    'source_domain_key',
                    'source_component_key',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'workspace_v2_canonical_work_sources'
        );

        Schema::dropIfExists(
            'workspace_v2_canonical_work_artifacts'
        );
    }
};
