<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('partnership_workspaces')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('kind', 16);
            $table->string('business_status', 16);
            $table->string('processing_status', 24)->default('needs_review');
            $table->longText('extracted_text')->nullable();
            $table->longText('reviewed_text')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedBigInteger('file_bytes')->nullable();
            $table->char('file_sha256', 64)->nullable();
            $table->boolean('extracted_partial')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->string('embedding_key', 160)->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'processing_status']);
        });
        Schema::create('workspace_knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('partnership_workspaces')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('workspace_knowledge_sources')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->text('content');
            $table->json('embedding');
            $table->string('embedding_key', 160);
            $table->index(['workspace_id', 'source_id']);
        });
        Schema::create('workspace_ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('partnership_workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->char('knowledge_signature', 64);
            $table->timestamps();
            $table->index(['workspace_id', 'user_id']);
        });
        Schema::create('workspace_ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('workspace_ai_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_ai_messages');
        Schema::dropIfExists('workspace_ai_conversations');
        Schema::dropIfExists('workspace_knowledge_chunks');
        Schema::dropIfExists('workspace_knowledge_sources');
    }
};
