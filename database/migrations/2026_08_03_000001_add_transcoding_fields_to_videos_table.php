<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('dyntube_video_id')->nullable()->change();
            $table->string('transcoding_status')->default('ready')->after('quiz_status'); // 'transcoding', 'ready', 'failed'
            $table->timestamp('transcoding_ready_at')->nullable()->after('transcoding_status');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('dyntube_video_id')->nullable(false)->change();
            $table->dropColumn(['transcoding_status', 'transcoding_ready_at']);
        });
    }
};
