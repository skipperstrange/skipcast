<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChannelGenresTable extends Migration
{
    public function up()
    {
        Schema::create('channel_genres', function(Blueprint $table)
        {
            $table->bigInteger('channel_id')->unsigned()->index();
            $table->integer('genre_id')->unsigned()->index();
            $table->foreign('genre_id')->references('id')->on('genres')->onDelete('cascade');
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->unique(['channel_id', 'genre_id']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('channel_genres');
    }
} 