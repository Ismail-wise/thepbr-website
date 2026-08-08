<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_chapters', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('chapter_number');
            $table->string('slug')->unique();

            $table->string('phase');
            $table->string('title_en');
            $table->string('title_mm');

            $table->text('description')->nullable();
            $table->json('topics')->nullable();

            $table->string('version')->default('v1');

            $table->boolean('is_published')
                ->default(false);

            $table->timestamps();

            $table->unique(
                ['chapter_number', 'version'],
                'course_chapter_number_version_unique'
            );

            $table->index(
                ['is_published', 'chapter_number']
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_chapters');
    }
};
