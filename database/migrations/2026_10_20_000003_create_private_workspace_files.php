<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up()
    {
        Schema::create('workspace_files', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained('schools'); $t->foreignId('uploader_id')->constrained('users');
            $t->string('kind',24); $t->unsignedBigInteger('resource_id'); $t->string('path')->unique();
            $t->string('name'); $t->string('mime',100); $t->unsignedBigInteger('size'); $t->string('sha256',64);
            $t->string('status',24)->default('quarantined'); $t->timestamp('scanned_at')->nullable(); $t->timestamps();
            $t->index(['school_id','kind','resource_id']);
        });
    }
    public function down() { Schema::dropIfExists('workspace_files'); }
};
