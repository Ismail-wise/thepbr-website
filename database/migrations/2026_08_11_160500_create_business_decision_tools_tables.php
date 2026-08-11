<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_feasibility_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('partnership_workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('project_name')->nullable();
            $table->json('inputs');
            $table->json('result');
            $table->timestamps();
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('business_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('partnership_workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('inputs');
            $table->json('result');
            $table->timestamps();
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_valuations');
        Schema::dropIfExists('business_feasibility_assessments');
    }
};
