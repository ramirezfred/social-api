<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CfdiImpuestosMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_impuestos', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('comprobante_id')->nullable();
            $table->float('TotalImpuestosTrasladados')->nullable();
            $table->float('TotalImpuestosRetenidos')->nullable();
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
        Schema::dropIfExists('cfdi_impuestos');
    }
}
