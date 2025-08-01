<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BotCitasMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bot_citas', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            
            $table->integer('bot_id')->nullable();
            $table->integer('cliente_id')->nullable();
            $table->string('nombre')->nullable();
            $table->string('telefono')->nullable(); 
            $table->string('email')->nullable(); 
            $table->text('tema')->nullable();
            $table->integer('day')->nullable();
            $table->integer('month')->nullable();
            $table->integer('year')->nullable();
            $table->integer('hour')->nullable();
            $table->integer('minutes')->nullable();
            $table->integer('status')->nullable();
            $table->integer('status_sms')->nullable();
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
        Schema::dropIfExists('bot_citas');
    }
}
