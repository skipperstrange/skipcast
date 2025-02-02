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
            $table->id(); // Auto-incrementing ID
            $table->bigInteger('channel_id')->unsigned()->index(); // Foreign key for channels
            $table->bigInteger('media_id')->unsigned()->index(); // Foreign key for media
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade'); // Foreign key constraint
            $table->foreign('media_id')->references('id')->on('media')->onDelete('cascade'); // Foreign key constraint
            $table->enum('active', ['active', 'inactive'])->default('active'); // Status of the association
            $table->unsignedInteger('list_order')->nullable()->default(0); // Auto-incrementing order
            $table->timestamps(); // Created at and updated at timestamps
            $table->softDeletes(); // Add soft deletes
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