<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Streaming state (on air/off air)
            $table->enum('state', ['on', 'off'])->default('off');
            
            // Channel visibility and availability
            $table->enum('active', ['active', 'inactive', 'trash'])->default('active');
            
            // Privacy setting
            $table->enum('privacy', ['private', 'public'])->default('public');
            
            // Social engagement metrics
            $table->bigInteger('views')->default(0);
            $table->bigInteger('likes')->default(0);
            
            // Listener metrics
            $table->integer('max_listeners')->default(10000);
            $table->integer('current_listeners')->default(0);
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('channels');
    }
}; 