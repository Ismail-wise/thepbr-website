<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'workspace_v2_operating_records',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id');
                $table->foreign(
                    'workspace_id',
                    'ws_v2_record_workspace_fk'
                )
                    ->references('id')
                    ->on('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->string('domain_key', 80);
                $table->string('component_key', 120);

                $table->foreignId(
                    'recorded_by_user_id'
                )->nullable();

                $table->foreign(
                    'recorded_by_user_id',
                    'ws_v2_record_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->string('state', 24);
                $table->string('title', 180)->nullable();
                $table->date('record_date')->nullable();
                $table->timestamp('effective_at')->nullable();
                $table->json('payload');
                $table->json('metadata')->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->index(
                    [
                        'workspace_id',
                        'domain_key',
                        'component_key',
                        'recorded_at',
                    ],
                    'ws_v2_record_component_time_idx'
                );

                $table->index(
                    ['workspace_id', 'record_date'],
                    'ws_v2_record_date_idx'
                );

                $table->index(
                    ['workspace_id', 'effective_at'],
                    'ws_v2_record_effective_idx'
                );
            }
        );

        Schema::create(
            'workspace_v2_actions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id');
                $table->foreign(
                    'workspace_id',
                    'ws_v2_action_workspace_fk'
                )
                    ->references('id')
                    ->on('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->string('action_domain_key', 80);
                $table->string('action_component_key', 120);

                $table->string('producer_domain_key', 80);
                $table->string('producer_component_key', 120);

                $table->foreignId(
                    'producer_canonical_revision_id'
                )->nullable();

                $table->foreign(
                    'producer_canonical_revision_id',
                    'ws_v2_action_prod_revision_fk'
                )
                    ->references('id')
                    ->on('workspace_v2_canonical_revisions')
                    ->restrictOnDelete();

                $table->foreignId(
                    'producer_operating_record_id'
                )->nullable();

                $table->foreign(
                    'producer_operating_record_id',
                    'ws_v2_action_prod_record_fk'
                )
                    ->references('id')
                    ->on('workspace_v2_operating_records')
                    ->restrictOnDelete();

                $table->foreignId(
                    'created_by_user_id'
                )->nullable();

                $table->foreign(
                    'created_by_user_id',
                    'ws_v2_action_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->string('owner_name', 160)->nullable();
                $table->string('priority', 20);
                $table->string('status', 24);
                $table->date('due_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(
                    ['workspace_id', 'status', 'due_date'],
                    'ws_v2_action_status_due_idx'
                );

                $table->index(
                    [
                        'workspace_id',
                        'action_domain_key',
                        'action_component_key',
                        'status',
                    ],
                    'ws_v2_action_component_status_idx'
                );

                $table->index(
                    [
                        'workspace_id',
                        'producer_domain_key',
                        'producer_component_key',
                    ],
                    'ws_v2_action_producer_idx'
                );
            }
        );

        Schema::create(
            'workspace_v2_action_canonical_sources',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id');
                $table->foreign(
                    'workspace_id',
                    'ws_v2_action_src_workspace_fk'
                )
                    ->references('id')
                    ->on('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->foreignId('action_id');
                $table->foreign(
                    'action_id',
                    'ws_v2_action_src_action_fk'
                )
                    ->references('id')
                    ->on('workspace_v2_actions')
                    ->restrictOnDelete();

                $table->foreignId('canonical_revision_id');
                $table->foreign(
                    'canonical_revision_id',
                    'ws_v2_action_src_revision_fk'
                )
                    ->references('id')
                    ->on('workspace_v2_canonical_revisions')
                    ->restrictOnDelete();

                $table->string('source_domain_key', 80);
                $table->string('source_component_key', 120);
                $table->unsignedInteger('source_revision');
                $table->timestamp('pinned_at');

                $table->unique(
                    ['action_id', 'canonical_revision_id'],
                    'ws_v2_action_src_unique'
                );

                $table->index(
                    ['workspace_id', 'canonical_revision_id'],
                    'ws_v2_action_src_revision_idx'
                );

                $table->index(
                    [
                        'action_id',
                        'source_domain_key',
                        'source_component_key',
                    ],
                    'ws_v2_action_src_component_idx'
                );
            }
        );

        Schema::create(
            'workspace_v2_operating_record_canonical_sources',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id');
                $table->foreign(
                    'workspace_id',
                    'ws_v2_record_src_workspace_fk'
                )
                    ->references('id')
                    ->on('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->foreignId('operating_record_id');
                $table->foreign(
                    'operating_record_id',
                    'ws_v2_record_src_record_fk'
                )
                    ->references('id')
                    ->on('workspace_v2_operating_records')
                    ->restrictOnDelete();

                $table->foreignId('canonical_revision_id');
                $table->foreign(
                    'canonical_revision_id',
                    'ws_v2_record_src_revision_fk'
                )
                    ->references('id')
                    ->on('workspace_v2_canonical_revisions')
                    ->restrictOnDelete();

                $table->string('source_domain_key', 80);
                $table->string('source_component_key', 120);
                $table->unsignedInteger('source_revision');
                $table->timestamp('pinned_at');

                $table->unique(
                    [
                        'operating_record_id',
                        'canonical_revision_id',
                    ],
                    'ws_v2_record_src_unique'
                );

                $table->index(
                    ['workspace_id', 'canonical_revision_id'],
                    'ws_v2_record_src_revision_idx'
                );

                $table->index(
                    [
                        'operating_record_id',
                        'source_domain_key',
                        'source_component_key',
                    ],
                    'ws_v2_record_src_component_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'workspace_v2_operating_record_canonical_sources'
        );

        Schema::dropIfExists(
            'workspace_v2_action_canonical_sources'
        );

        Schema::dropIfExists('workspace_v2_actions');

        Schema::dropIfExists(
            'workspace_v2_operating_records'
        );
    }
};
