<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up() { Schema::create('workspace_runtime',function(Blueprint $t) { $t->string('component')->primary(); $t->timestamp('seen_at'); }); }
    public function down() { Schema::dropIfExists('workspace_runtime'); }
};
