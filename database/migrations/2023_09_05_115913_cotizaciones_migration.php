<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CotizacionesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('bot_id')->nullable();
            $table->integer('cliente_id')->nullable();
            $table->integer('status')->nullable();
            $table->float('subtotal')->nullable();
            $table->float('envio')->nullable();
            $table->float('total')->nullable();
            $table->string('cliente')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
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
        Schema::dropIfExists('cotizaciones');
    }
}
