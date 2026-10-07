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
        Schema::table('students', function (Blueprint $table) {
            $table->integer('class_section_id')->nullable()->change();
            $table->integer('category_id')->nullable()->change();
            $table->string('admission_no')->nullable()->change();
            $table->date('admission_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->integer('class_section_id')->nullable()->change();
            $table->integer('category_id')->nullable()->change();
            $table->string('admission_no')->nullable()->change();
            $table->date('admission_date')->nullable()->change();
        });
    }
};
