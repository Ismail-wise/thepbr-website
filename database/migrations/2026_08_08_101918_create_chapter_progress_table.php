<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('course_chapter_id')
                ->constrained('course_chapters')
                ->cascadeOnDelete();

            $table->foreignId('student_enrollment_id')
                ->nullable()
                ->constrained('student_enrollments')
                ->nullOnDelete();

            $table->string('status')
                ->default('not_started');

            $table->unsignedTinyInteger('progress_percent')
                ->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'course_chapter_id'],
                'chapter_progress_user_chapter_unique'
            );

            $table->index(
                ['user_id', 'status']
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_progress');
    }
};
