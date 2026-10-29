<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('workspace_ad_free_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->nullable()->constrained('schools');
            $t->foreignId('district_id')->nullable()->constrained('workspace_districts');
            $t->foreignId('requested_by')->constrained('users'); $t->text('reason');
            $t->string('status', 16)->default('pending'); $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('workspace_ad_free_requests'); }
};
