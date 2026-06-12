<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SuppliersMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('razon_social')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->text('direccion')->nullable();
            $table->string('contacto')->nullable();
            $table->boolean('status')->default(true);
            $table->string('categoria')->nullable();
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
        Schema::dropIfExists('suppliers');
    }
}
