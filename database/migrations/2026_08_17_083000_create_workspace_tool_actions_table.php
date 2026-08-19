<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_tool_actions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('workspace_id')
                ->constrained('partnership_workspaces')
                ->cascadeOnDelete();

            $table->foreignId('chapter_tool_id')
                ->nullable()
                ->constrained('chapter_tools')
                ->nullOnDelete();

            $table->foreignId('source_tool_session_id')
                ->nullable()
                ->constrained('tool_sessions')
                ->nullOnDelete();

            $table->foreignId('workspace_tool_output_id')
                ->nullable()
                ->constrained('workspace_tool_outputs')
                ->nullOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('owner_name', 160)->nullable();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 24)->default('open')->index();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->json('operating_context')->nullable();
            $table->timestamps();

            $table->index(
                ['workspace_id', 'status', 'due_date'],
                'ws_tool_actions_status_due_idx'
            );

            $table->index(
                ['workspace_id', 'chapter_tool_id'],
                'ws_tool_actions_tool_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_tool_actions');
    }
};
