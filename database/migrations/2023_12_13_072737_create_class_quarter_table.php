<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('class_quarter', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quarter_id');
            $table->string('session_year');
            $table->bigInteger('class_id')->unsigned()->index();
            $table->string('status');

            $table->timestamps();
            $table->foreign('quarter_id')->references('id')->on('quarters')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('class_quarter');
    }
};
