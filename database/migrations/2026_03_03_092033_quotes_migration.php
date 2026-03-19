<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class QuotesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable();
            
            $table->string('cliente')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();

            $table->string('estado', 20)->default('en curso'); // en curso, finalizada, cancelada
            
            // Totales
            $table->string('moneda')->default('MXN'); //MXN y USD
            $table->decimal('envio', 15, 2)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->decimal('adelanto_monto', 15, 2)->default(0);
            $table->string('adelanto_tipo', 20)->nullable(); // efectivo, transferencia
            $table->decimal('adelanto_restante', 15, 2)->default(0);
            $table->timestamp('adelanto_fecha')->nullable();
            $table->string('adelanto_referencia', 255)->nullable();

            // Campos de pago restante / pago completo
            $table->decimal('restante_monto', 15, 2)->default(0);
            $table->string('restante_tipo', 20)->nullable(); // efectivo, transferencia
            $table->timestamp('restante_fecha')->nullable();
            $table->string('restante_referencia', 255)->nullable();

            // Estado del pago general
            $table->string('pago_estado', 20)->default('pendiente'); // pendiente | adelantado | pagado
            
            $table->string('tipo_entrega', 20)->nullable(); // plaza, envio

            $table->string('api_tipo_pago')->nullable();
            $table->string('conekta_id')->nullable();
            $table->string('conekta_customer_id')->nullable();
            $table->string('stripe_id')->nullable();
            $table->boolean('flag_reembolso')->default(false);

            $table->text('notas')->nullable();
            $table->string('pdf')->nullable();
            $table->boolean('eliminado')->default(false);
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
        Schema::dropIfExists('quotes');
    }
}
