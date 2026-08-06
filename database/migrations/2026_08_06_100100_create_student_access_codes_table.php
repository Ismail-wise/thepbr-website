<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_access_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')
                ->nullable()
                ->constrained('class_sessions')
                ->nullOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->text('code_encrypted');
            $table->string('code_last4', 4);
            $table->string('status', 20)->default('available')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('used_by_user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_access_codes');
    }
};
