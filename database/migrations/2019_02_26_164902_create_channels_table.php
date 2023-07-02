<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChannelsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('channels', function(Blueprint $table)
		{
            $table->bigIncrements('id');
			$table->string('name', 60);
			$table->text('description')->nullable();
			$table->enum('state', array('start','stop'))->default('stop');
			$table->enum('active', array('active','inactive','trash'))->default('active');
            $table->enum('privacy', array('private','public'))->default('public');
            $table->bigInteger('views')->default(0);
            $table->bigInteger('likes')->default(0);
            $table->unsignedBigInteger('user_id')->references('id')->on('users')->onDelete('cascade');
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
		Schema::drop('channels');
	}

}
