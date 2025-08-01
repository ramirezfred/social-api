<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CfdiProductosMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_productos', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('empresa_id')->nullable();
            $table->string('ClaveProdServ')->nullable();
            $table->string('NoIdentificacion',1000)->nullable();
            $table->float('Cantidad')->nullable();
            $table->string('ClaveUnidad')->nullable();
            $table->string('Unidad')->nullable();
            $table->string('Descripcion',1000)->nullable();
            $table->float('ValorUnitario')->nullable();
            $table->float('Importe')->nullable();
            $table->float('Descuento')->nullable();
            $table->string('ObjetoImp')->nullable();
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
        Schema::dropIfExists('cfdi_productos');
    }
}
