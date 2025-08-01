<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CfdiArchivosMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cfdi_archivos', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('comprobante_id')->nullable();
            $table->string('xml_archivo')->nullable();
            $table->text('xml')->nullable();
            $table->text('png')->nullable();
            $table->string('pdf')->nullable();
            $table->string('imagen')->nullable();
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
        Schema::dropIfExists('cfdi_archivos');
    }
}
