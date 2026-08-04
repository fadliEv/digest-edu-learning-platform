<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add transcript column to videos table.
     *
     * Stores timestamped transcript / timeline text (e.g. "00:30 - Penjelasan usus buntu")
     * so AI can generate 100% accurate questions tied to exact minutes in the video.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->longText('transcript')
                ->nullable()
                ->after('description')
                ->comment('Timestamped video transcript / timeline for accurate AI question & timestamp generation');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('transcript');
        });
    }
};
