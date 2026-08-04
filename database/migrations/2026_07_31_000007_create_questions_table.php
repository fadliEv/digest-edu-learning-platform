<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->text('question_text');
            // JSON: {"A": "...", "B": "...", "C": "...", "D": "..."}
            $table->json('options');
            // Correct answer key: 'A' | 'B' | 'C' | 'D'
            $table->char('correct_answer', 1);
            $table->unsignedSmallInteger('order_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
