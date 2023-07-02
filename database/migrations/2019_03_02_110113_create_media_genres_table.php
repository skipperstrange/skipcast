<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMediaGenresTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('media_genres', function(Blueprint $table)
		{
            $table->unsignedBigInteger('media_id')->references('id')->on('channels')->onDelete('cascade');
            $table->unsignedBigInteger('genre_id')->references('id')->on('genres')->onDelete('cascade');
            $table->primary(['media_id', 'genre_id']);
            $table->timestamps();
            $table->softDeletes(); // Add soft deletes
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('media_genres');
	}

}
