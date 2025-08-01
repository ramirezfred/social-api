<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PedidoDetalleMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pedido_detalle', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('pedido_id')->nullable();
            $table->integer('producto_id')->nullable();
            $table->integer('color_id')->nullable();
            $table->integer('tipo_id')->nullable();
            $table->integer('cantidad')->nullable();
            $table->float('precio_initario')->nullable();
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
        Schema::dropIfExists('pedido_detalle');
    }
}
