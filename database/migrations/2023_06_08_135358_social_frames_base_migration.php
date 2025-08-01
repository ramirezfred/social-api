<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SocialFramesBaseMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('social_frames_base', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            
            $table->string('nombre')->nullable();
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
        Schema::dropIfExists('social_frame_base');
    }
}
