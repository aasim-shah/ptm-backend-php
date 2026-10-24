<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('workspace_profiles', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users');
            $t->string('timezone', 64)->default('UTC'); $t->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('workspace_profiles'); }
};
