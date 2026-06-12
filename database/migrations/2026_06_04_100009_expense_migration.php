<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExpenseMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->decimal('monto', 15, 2);
            $table->text('concepto')->nullable(); 
            $table->date('fecha');

            $table->string('corte_c_estado')->default('pendiente'); // 'pendiente' | 'cerrado'
            $table->unsignedBigInteger('cash_close_c_id')->nullable(); // Llave foránea al corte
            
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
        Schema::dropIfExists('expenses');
    }
}
