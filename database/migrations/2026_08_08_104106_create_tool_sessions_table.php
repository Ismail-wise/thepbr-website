<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('chapter_tool_id')
                ->constrained('chapter_tools')
                ->cascadeOnDelete();

            $table->foreignId('workspace_id')
                ->nullable()
                ->constrained('partnership_workspaces')
                ->nullOnDelete();

            $table->string('business_stage')
                ->default('new');

            $table->string('scenario_name')
                ->nullable();

            $table->string('status')
                ->default('draft');

            $table->json('input_data')
                ->nullable();

            $table->json('result_data')
                ->nullable();

            $table->timestamp('started_at')
                ->nullable();

            $table->timestamp('last_saved_at')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamps();

            $table->index(
                [
                    'user_id',
                    'chapter_tool_id',
                    'status',
                ],
                'tool_sessions_user_tool_status_index'
            );

            $table->index(
                [
                    'workspace_id',
                    'chapter_tool_id',
                ],
                'tool_sessions_workspace_tool_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_sessions');
    }
};
