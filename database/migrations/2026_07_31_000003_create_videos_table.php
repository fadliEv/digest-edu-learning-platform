<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('dyntube_video_id');
            $table->string('dyntube_iframe_link')->nullable();
            $table->string('dyntube_thumbnail')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_published')->default(false);
            // quiz_status: belum | proses | aktif | gagal
            $table->string('quiz_status')->default('belum');
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
