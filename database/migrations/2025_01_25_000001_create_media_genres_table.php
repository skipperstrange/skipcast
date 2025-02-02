<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMediaGenresTable extends Migration
{
    public function up()
    {
        Schema::create('media_genres', function(Blueprint $table)
        {
            $table->bigInteger('media_id')->unsigned()->index();
            $table->integer('genre_id')->unsigned()->index();
            $table->foreign('genre_id')->references('id')->on('genres')->onDelete('cascade');
            $table->foreign('media_id')->references('id')->on('media')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes(); // Added soft delete
        });
    }

    public function down()
    {
        Schema::drop('media_genres');
    }
} 