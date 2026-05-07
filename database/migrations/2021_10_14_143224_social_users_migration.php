<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SocialUsersMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('social_users', function (Blueprint $table) {
            //$table->id();
            $table->increments('id');
            $table->integer('tipo')->nullable();
            $table->integer('status')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->dateTime('last_login')->nullable();

            $table->string('nombre')->nullable();
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
        Schema::dropIfExists('social_users');
    }
}
