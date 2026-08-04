<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->unsignedSmallInteger('score')->default(0); // 0–100
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
