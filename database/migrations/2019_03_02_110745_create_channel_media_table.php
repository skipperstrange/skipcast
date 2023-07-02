<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChannelMediaTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('channel_media', function(Blueprint $table)
		{

            $table->unsignedBigInteger('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->unsignedBigInteger('media_id')->references('id')->on('media')->onDelete('cascade');
            $table->primary(['channel_id', 'media_id']);
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
		Schema::drop('channel_media');
	}

}
