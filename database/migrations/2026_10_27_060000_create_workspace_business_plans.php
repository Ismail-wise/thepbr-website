<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_business_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('partnership_workspaces')->cascadeOnDelete();
            $table->json('draft')->nullable();
            $table->unsignedInteger('draft_base_revision')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        Schema::create('workspace_business_plan_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_plan_id')->constrained('workspace_business_plans')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedInteger('based_on_revision')->nullable();
            $table->json('content');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('knowledge_source_id')->nullable()->constrained('workspace_knowledge_sources')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['business_plan_id', 'revision'], 'business_plan_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_business_plan_revisions');
        Schema::dropIfExists('workspace_business_plans');
    }
};
