<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CfdiComprobanteMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_comprobante', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('bot_id')->nullable();
            $table->integer('cliente_id')->nullable();
            $table->integer('status')->nullable();
            $table->integer('flag_cancelada')->nullable();
            $table->string('Serie')->nullable();
            $table->string('Folio')->nullable();
            $table->string('Fecha')->nullable();
            $table->text('Sello')->nullable();
            $table->string('FormaPago')->nullable();
            $table->string('NoCertificado')->nullable();
            $table->text('Certificado')->nullable();
            $table->string('CondicionesDePago',1000)->nullable();
            $table->float('Subtotal')->nullable();
            $table->float('Descuento')->nullable();
            $table->string('Moneda')->nullable();
            $table->string('TipoCambio')->nullable();
            $table->float('Total')->nullable();
            $table->string('TipoDeComprobante')->nullable();
            $table->string('Exportacion')->nullable();
            $table->string('MetodoPago')->nullable();
            $table->string('LugarExpedicion')->nullable();
            $table->string('Confirmacion')->nullable();
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
        Schema::dropIfExists('cfdi_comprobante');
    }
}
