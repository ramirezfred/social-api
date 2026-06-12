<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CashClosesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cash_closes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo'); // 'proveedor', 'ingresos_gastos', 'comision_envios'
            $table->decimal('monto', 15, 2); // Monto final neto del corte
            $table->unsignedBigInteger('supplier_id')->nullable(); // Solo si tipo es 'proveedor'
            $table->string('metodo_pago')->nullable(); // 'efectivo', 'transferencia'

            $table->decimal('total_vendido', 15, 2)->nullable();
            $table->decimal('total_comision', 15, 2)->nullable();
            $table->decimal('total_envios', 15, 2)->nullable();
            $table->decimal('total_gastos', 15, 2)->nullable();

            $table->timestamps(); // created_at marcará el inicio del siguiente periodo
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cash_closes');
    }
}
