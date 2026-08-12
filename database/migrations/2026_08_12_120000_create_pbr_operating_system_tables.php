<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->removeEmptyPartialTables();

        Schema::create('workspace_partner_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')
                ->constrained('partnership_workspaces')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->uuid('partner_key');
            $table->string('display_name', 160);
            $table->string('status', 24)->default('active')->index();
            $table->json('profile_data')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'partner_key']);
            $table->index(['workspace_id', 'user_id']);
        });

        Schema::create('workspace_operating_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')
                ->constrained('partnership_workspaces')
                ->cascadeOnDelete();
            $table->string('domain_key', 60)->index();
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 24)->default('draft')->index();
            $table->string('schema_version', 24)->default('v1');
            $table->json('payload');
            $table->json('summary')->nullable();
            $table->foreignId('generated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('agreed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['workspace_id', 'domain_key', 'revision'],
                'workspace_domain_revision_unique'
            );
            $table->index(
                ['workspace_id', 'domain_key', 'status'],
                'ws_snapshot_domain_status_idx'
            );
        });

        Schema::create('workspace_operating_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')
                ->constrained('partnership_workspaces')
                ->cascadeOnDelete();
            $table->foreignId('chapter_tool_id')
                ->nullable()
                ->constrained('chapter_tools')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('record_type', 60)->index();
            $table->string('status', 24)->default('active')->index();
            $table->string('title', 180)->nullable();
            $table->date('record_date')->nullable()->index();
            $table->timestamp('effective_at')->nullable()->index();
            $table->json('data');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['workspace_id', 'record_type', 'status'],
                'ws_record_type_status_idx'
            );
            $table->index(['workspace_id', 'chapter_tool_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_operating_records');
        Schema::dropIfExists('workspace_operating_snapshots');
        Schema::dropIfExists('workspace_partner_profiles');
    }

    private function removeEmptyPartialTables(): void
    {
        $tables = [
            'workspace_partner_profiles',
            'workspace_operating_snapshots',
            'workspace_operating_records',
        ];

        $existing = array_values(array_filter(
            $tables,
            static fn (string $table): bool => Schema::hasTable($table)
        ));

        if ($existing === []) {
            return;
        }

        foreach ($existing as $table) {
            if (DB::table($table)->exists()) {
                throw new \RuntimeException(
                    "Refusing to replace partial operating-system table {$table} because it contains data."
                );
            }
        }

        foreach (array_reverse($tables) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
