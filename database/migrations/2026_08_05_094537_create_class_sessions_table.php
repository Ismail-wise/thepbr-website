<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('starts_on');                    // past vs upcoming is decided by this
            $table->date('ends_on')->nullable();          // for 2-day classes
            $table->string('mode')->default('in_person'); // in_person | online
            $table->string('location');
            $table->string('time_note')->nullable();      // "နံနက် ၉:၀၀ – ညနေ ၄:၀၀"
            $table->string('fee')->nullable();            // free text: kyat or baht
            $table->unsignedSmallInteger('capacity')->default(0);
            $table->unsignedSmallInteger('enrolled')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
