<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVideoFieldsToMediaTable extends Migration
{
    public function up()
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('resolution')->nullable(); // Video resolution (e.g., 1920x1080)
            $table->string('codec')->nullable(); // Video codec (e.g., H.264)
            $table->integer('frame_rate')->nullable(); // Frame rate (e.g., 30)
            $table->string('aspect_ratio')->nullable(); // Aspect ratio (e.g., 16:9)
            $table->integer('bitrate')->nullable(); // Bitrate (e.g., 5000 kbps)
        });
    }

    public function down()
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('resolution');
            $table->dropColumn('codec');
            $table->dropColumn('frame_rate');
            $table->dropColumn('aspect_ratio');
            $table->dropColumn('bitrate');
        });
    }
} 