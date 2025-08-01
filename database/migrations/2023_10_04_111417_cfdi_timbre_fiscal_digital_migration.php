<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CfdiTimbreFiscalDigitalMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_timbre_fiscal_digital', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('comprobante_id')->nullable();
            $table->string('Version')->nullable();
            $table->string('UUID')->nullable();
            $table->string('FechaTimbrado')->nullable();
            $table->string('RfcProvCertif')->nullable();
            $table->string('SelloCFD',1000)->nullable();
            $table->string('NoCertificadoSAT')->nullable();
            $table->string('SelloSAT',1000)->nullable();
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
        Schema::dropIfExists('cfdi_timbre_fiscal_digital');
    }
}
