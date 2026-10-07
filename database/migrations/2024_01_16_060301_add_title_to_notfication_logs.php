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
        try {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->string('title')->nullable();
            });
        }catch (Exception $e){

        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        try {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->dropColumn('title');
            });
        }catch (Exception $e){

        }
    }
};
