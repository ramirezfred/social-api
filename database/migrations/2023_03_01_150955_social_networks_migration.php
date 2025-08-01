<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SocialNetworksMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('social_networks', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');

            $table->integer('brand_id')->nullable();
            $table->integer('tipo')->nullable();
            $table->integer('login')->nullable();
            $table->string('alias')->nullable();
            $table->string('user')->nullable();
            $table->string('password')->nullable();
            $table->string('page_id')->nullable();
            $table->string('page_name')->nullable();
            $table->string('page_image')->nullable();
            $table->string('access_token')->nullable();

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
        Schema::dropIfExists('social_networks');
    }
}
