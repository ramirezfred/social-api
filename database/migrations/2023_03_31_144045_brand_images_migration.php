<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BrandImagesMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_images', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');

            $table->integer('brand_id')->nullable();
            $table->integer('aprobada')->nullable();
            $table->integer('publicada')->nullable();
            $table->string('url')->nullable();
            $table->string('url_allow_origin')->nullable();
            $table->text('base64')->nullable();

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
        Schema::dropIfExists('brand_images');
    }
}
