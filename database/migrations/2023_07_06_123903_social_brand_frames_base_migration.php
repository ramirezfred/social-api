<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SocialBrandFramesBaseMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('social_brand_frames_base', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('brand_id')->nullable();
            $table->integer('frame_base_id')->nullable();
            $table->integer('frame_id')->nullable();
            
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
        Schema::dropIfExists('social_brand_frames_base');
    }
}
