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
        Schema::dropIfExists('fees_sub_types');
        Schema::dropIfExists('fees_paid');
        Schema::dropIfExists('fees');

        Schema::create('fees_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable(true);
            $table->tinyInteger('choiceable')->comment('0 - no 1 - yes');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fees_classes', function (Blueprint $table) {
            $table->id();
            $table->integer('class_id');
            $table->integer('fees_type_id');
            $table->integer('amount');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fees_choiceables', function (Blueprint $table) {
            $table->id();
            $table->integer('student_id');
            $table->integer('class_id');
            $table->integer('fees_type_id')->nullable(true);
            $table->tinyInteger('is_due_charges')->comment('0 - no 1 - yes');
            $table->integer('total_amount');
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('student_id');
            $table->integer('class_id');
            $table->integer('parent_id');
            $table->tinyInteger('payment_gateway')->comment('1 - razorpay 2 - stripe');
            $table->string('order_id')->comment('order_id / payment_intent_id');
            $table->string('payment_id')->nullable(true);
            $table->string('payment_signature')->nullable(true);
            $table->tinyInteger('payment_status')->comment('0 - failed 1 - succeed 2 - pending');
            $table->integer('total_amount');
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fees_paids', function (Blueprint $table) {
            $table->id();
            $table->integer('parent_id')->nullable(true);
            $table->integer('student_id');
            $table->integer('class_id');
            $table->tinyInteger('mode')->comment('0 - cash 1 - cheque 2 - online');
            $table->string('payment_transaction_id')->nullable(true);
            $table->string('cheque_no')->nullable(true);
            $table->integer('total_amount');
            $table->date('date');
            $table->integer('session_year_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fees_types');
        Schema::dropIfExists('fees_classes');
        Schema::dropIfExists('fees_choiceables');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('fees_paids');
    }
};
