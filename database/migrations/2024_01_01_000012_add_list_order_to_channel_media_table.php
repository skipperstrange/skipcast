<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddListOrderToChannelMediaTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('channel_media', function (Blueprint $table) {
            $table->unsignedInteger('list_order')->nullable()->default(0)->after('active'); // Add list_order column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_media', function (Blueprint $table) {
            $table->dropColumn('list_order'); // Remove list_order column
        });
    }
} 