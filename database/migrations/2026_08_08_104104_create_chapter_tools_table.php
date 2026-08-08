<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_tools', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_chapter_id')
                ->constrained('course_chapters')
                ->cascadeOnDelete();

            $table->string('tool_key');
            $table->string('slug');

            $table->string('title_en');
            $table->string('title_mm')->nullable();

            $table->string('tool_type');

            $table->text('description')->nullable();

            $table->unsignedTinyInteger('sort_order')
                ->default(1);

            $table->string('version')
                ->default('v1');

            $table->boolean('supports_new_business')
                ->default(true);

            $table->boolean('supports_existing_business')
                ->default(true);

            $table->boolean('is_published')
                ->default(false);

            $table->timestamps();

            $table->unique(
                ['course_chapter_id', 'tool_key', 'version'],
                'chapter_tool_key_version_unique'
            );

            $table->unique(
                ['course_chapter_id', 'slug', 'version'],
                'chapter_tool_slug_version_unique'
            );

            $table->index(
                [
                    'course_chapter_id',
                    'is_published',
                    'sort_order',
                ],
                'chapter_tools_display_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_tools');
    }
};
