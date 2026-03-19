<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class QuoteDetailsMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quote_details', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('quote_id');
            // $table->unsignedBigInteger('product_id'); // relación directa producto
            $table->unsignedBigInteger('supplier_id'); // relación directa proveedor

            $table->string('modelo')->nullable();
            $table->string('talla')->nullable();
            $table->string('color')->nullable();

            $table->decimal('cantidad', 15, 4);
            $table->decimal('precio_unitario', 15, 4);
            $table->decimal('porcentaje_desc', 5, 2)->default(0);
            $table->decimal('porcentaje_impuesto', 5, 2)->default(0); // IVA aplicado (0 o 16)
            
            // Totales por línea
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('impuesto', 15, 4)->default(0);
            $table->decimal('descuento', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('cascade');
            // $table->foreign('supplier_id')->references('id')->on('suppliers');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('quote_details');
    }
}
