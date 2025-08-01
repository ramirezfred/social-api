<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BotsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bots', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');

            $table->integer('user_id')->nullable();
            $table->integer('status')->nullable();
            $table->string('nombre')->nullable();
            $table->string('telefono')->nullable();
            $table->text('access_token')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chat_bots');
    }
}
