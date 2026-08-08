<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'workspace_tool_outputs',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('workspace_id')
                    ->constrained('partnership_workspaces')
                    ->cascadeOnDelete();

                $table->foreignId('chapter_tool_id')
                    ->constrained('chapter_tools')
                    ->cascadeOnDelete();

                $table->foreignId('source_tool_session_id')
                    ->nullable()
                    ->constrained('tool_sessions')
                    ->nullOnDelete();

                $table->unsignedInteger('revision')
                    ->default(1);

                $table->string('status')
                    ->default('draft');

                $table->json('output_data')
                    ->nullable();

                $table->foreignId('generated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('generated_at')
                    ->nullable();

                $table->timestamp('agreed_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'workspace_id',
                        'chapter_tool_id',
                        'revision',
                    ],
                    'workspace_tool_output_revision_unique'
                );

                $table->index(
                    [
                        'workspace_id',
                        'status',
                    ],
                    'workspace_tool_outputs_status_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'workspace_tool_outputs'
        );
    }
};
