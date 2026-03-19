<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PublicationImageMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('publication_image', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('publication_id');
            $table->foreign('publication_id')->references('id')->on('publications')->onDelete('cascade');
            
            $table->string('image_path')->nullable();

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
        Schema::dropIfExists('publication_image');
    }
}
