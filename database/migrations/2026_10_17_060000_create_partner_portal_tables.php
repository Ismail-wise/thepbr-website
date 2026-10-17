<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
        Schema::create('portal_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('portal_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('portal_saved_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('title', 120);
            $table->json('input');
            $table->json('output');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('portal_access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 30);
            $table->unsignedInteger('days')->nullable();
            $table->json('before_state');
            $table->json('after_state');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_access_events');
        Schema::dropIfExists('portal_saved_items');
        Schema::dropIfExists('portal_messages');
        Schema::dropIfExists('portal_conversations');
    }
};
