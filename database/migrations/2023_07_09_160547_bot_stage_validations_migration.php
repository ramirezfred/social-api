<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BotStageValidationsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bot_stage_validations', function (Blueprint $table) {
            
            //$table->id();
            $table->increments('id');
            $table->integer('stage_id')->nullable();
            $table->integer('tipo')->nullable(); //1=con prompt 2=con funcion
            $table->text('prompt')->nullable();
            $table->string('funcion')->nullable(); //nombre de la funcion
            $table->integer('tipo_resp')->nullable(); //1=con prompt 2=predefinida
            $table->text('prompt_resp')->nullable();
            $table->text('text_resp')->nullable();
            

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
        Schema::dropIfExists('bot_flow_validations');
    }
}
