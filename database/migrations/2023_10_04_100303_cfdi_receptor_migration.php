<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CfdiReceptorMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_receptor', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('comprobante_id')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('DomicilioFiscalReceptor')->nullable();
            $table->string('ResidenciaFiscal')->nullable();
            $table->string('NumRegIdTrib')->nullable();
            $table->string('RegimenFiscalReceptor')->nullable();
            $table->string('UsoCFDI')->nullable();
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
        Schema::dropIfExists('cfdi_receptor');
    }
}
