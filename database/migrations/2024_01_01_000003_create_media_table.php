<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 254)->nullable();
            $table->integer('track')->nullable(); // Changed from 'int' to 'integer'
            $table->string('album', 120)->nullable();
            $table->string('year', 4)->nullable();
            $table->string('artist', 120)->nullable();
            $table->enum('type', ['audio/mp3', 'audio/mpeg'])->nullable();
            $table->enum('channelmode', ['mono', 'stereo'])->nullable();
            $table->enum('public', ['public', 'private'])->default('public');
            $table->enum('downloadable', ['yes', 'no'])->default('no');
            $table->bigInteger('size')->default(0)->nullable();
            $table->bigInteger('views')->default(0);
            $table->bigInteger('likes')->default(0);
            $table->bigInteger('user_id')->unsigned()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
}; 