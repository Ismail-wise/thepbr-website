<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table): void {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->string('category')->nullable();

            // The YouTube video ID only, never a full URL. IDs are exactly 11
            // characters and are what both the embed and the thumbnail need;
            // storing a pasted URL would mean re-parsing it on every render and
            // would break if someone saved a link with tracking parameters.
            $table->string('youtube_id', 32);

            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // Public listings filter on published_at and order by it.
            $table->index('published_at');
            $table->index(['category', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
