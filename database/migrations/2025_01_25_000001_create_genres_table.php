<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGenresTable extends Migration
{
    public function up()
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->increments('id');
            $table->string('genre', 40);
            $table->string('slug')->unique();
            $table->timestamps();
            $table->softDeletes(); // Added soft delete
        });
    }

    public function down()
    {
        Schema::dropIfExists('genres');
    }
} 