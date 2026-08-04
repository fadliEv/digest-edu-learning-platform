<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add video_timestamp_seconds to questions table.
     *
     * Stores the approximate second-mark in the video where the topic
     * of each question is explained, so admins can verify relevance.
     * Example: 125 = 2 menit 05 detik dalam video.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Nullable → older questions / manual questions without timestamp remain valid
            $table->unsignedSmallInteger('video_timestamp_seconds')
                ->nullable()
                ->after('order_number')
                ->comment('Second-mark in the video where the related topic is explained (null = not specified)');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('video_timestamp_seconds');
        });
    }
};
