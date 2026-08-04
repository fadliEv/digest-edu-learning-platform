<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->unsignedInteger('last_position')->default(0); // in seconds
            $table->timestamps();

            // Each user has one resume per video
            $table->unique(['user_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_resumes');
    }
};
