<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ProductosMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('productos', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('cliente_id')->nullable();
            $table->string('nombre')->nullable();
            $table->integer('status')->nullable();
            $table->string('url')->nullable(); //imagen
            $table->text('descripcion')->nullable();
            $table->float('precio')->nullable();
            $table->integer('eliminado')->nullable();

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
        Schema::dropIfExists('productos');
    }
}
