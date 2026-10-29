<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up()
    {
        Schema::table('account_security', function (Blueprint $t) { $t->unsignedInteger('session_version')->default(1); });
        Schema::table('account_sessions', function (Blueprint $t) { $t->unsignedInteger('version')->default(1); });
    }
    public function down()
    {
        Schema::table('account_sessions', function (Blueprint $t) { $t->dropColumn('version'); });
        Schema::table('account_security', function (Blueprint $t) { $t->dropColumn('session_version'); });
    }
};
