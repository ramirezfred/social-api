<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BotFlowStagesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bot_flow_stages', function (Blueprint $table) {
            
            //$table->id();
            $table->increments('id');
            $table->integer('flow_id')->nullable();
            $table->integer('item')->nullable(); //indica el orden en el flujo
            $table->integer('tipo')->nullable(); //1=con prompt 2=con texto predefinido
            $table->text('prompt')->nullable();
            $table->text('text')->nullable();

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
        Schema::dropIfExists('bot_flow_stages');
    }
}
