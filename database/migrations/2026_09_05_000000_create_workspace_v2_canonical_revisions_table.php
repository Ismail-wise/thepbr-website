<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'workspace_v2_canonical_revisions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id')
                    ->constrained('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->string('domain_key', 80);

                $table->string('component_key', 120);

                $table->string('component_type', 24);

                $table->unsignedInteger('revision');

                $table->string('state', 32);

                $table->json('payload');

                $table->json('approval_decision');

                $table->foreignId('published_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('published_at');

                $table->timestamp('superseded_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'workspace_id',
                        'domain_key',
                        'component_key',
                        'revision',
                    ],
                    'ws_v2_canonical_revision_unique'
                );

                $table->index(
                    [
                        'workspace_id',
                        'domain_key',
                        'component_key',
                        'state',
                    ],
                    'ws_v2_canonical_current_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'workspace_v2_canonical_revisions'
        );
    }
};
