<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChannelMediaTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channel_media', function (Blueprint $table) {
            $table->bigIncrements('id'); // Use bigIncrements for the primary key
            $table->bigInteger('channel_id')->unsigned(); // Added column name
            $table->bigInteger('media_id')->unsigned();  // Added column name
            $table->enum('active', ['active', 'inactive'])->default('active'); // Status of the association
            $table->integer('list_order')->unsigned()->nullable()->default(0);
            $table->timestamps(); // Created at and updated at timestamps
            $table->softDeletes(); // Add soft deletes

            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('media_id')->references('id')->on('media')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_media');
    }
} 