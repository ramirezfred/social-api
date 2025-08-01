<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BotConfigMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bot_config', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            
            $table->integer('bot_id')->nullable();
            $table->text('palabra_clave')->nullable();
            $table->text('prompt')->nullable();
            $table->text('acciones')->nullable();
            $table->integer('tipo')->nullable();
            $table->integer('status')->nullable();
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
        Schema::dropIfExists('bot_config');
    }
}
