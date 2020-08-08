<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestConnectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('request_connections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('before_rev');
            $table->unsignedBigInteger('after_rev');
            $table->timestamps();

            $table->foreign('before_rev')->references('id')->on('requests')->onDelete('cascade');
            $table->foreign('after_rev')->references('id')->on('requests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('request_connections');
    }
}
