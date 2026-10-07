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
        // Drop cloumn choiceable in fees types table
        Schema::table('fees_types', function (Blueprint $table) {
            $table->dropColumn('choiceable');
        });

        // Add cloumn choiceable in fees classes table
        Schema::table('fees_classes', function (Blueprint $table) {
            $table->tinyInteger('choiceable')->comment('0 - no 1 - yes')->after('fees_type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert add cloumn choiceable in fees types table
        Schema::table('fees_types', function (Blueprint $table) {
            $table->tinyInteger('choiceable')->comment('0 - no 1 - yes')->after('description');
        });

        // Revert drop cloumn choiceable in fees classes table
        Schema::table('fees_classes', function (Blueprint $table) {
            $table->dropColumn('choiceable');
        });
    }
};
