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
        Schema::create('paid_installment_fees', function (Blueprint $table) {
            $table->id();

            //foreign key class id
            $table->bigInteger('class_id')->unsigned()->index();
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');

            //foreign key student id
            $table->bigInteger('student_id')->unsigned()->index();
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');

            //foreign key parent id
            $table->bigInteger('parent_id')->unsigned()->index();
            $table->foreign('parent_id')->references('id')->on('parents')->onDelete('cascade');

            //foreign key installment fee id
            $table->bigInteger('installment_fee_id')->unsigned()->index();
            $table->foreign('installment_fee_id')->references('id')->on('installment_fees')->onDelete('cascade');


            //foreign key session year id
            $table->bigInteger('session_year_id')->unsigned()->index();
            $table->foreign('session_year_id')->references('id')->on('session_years')->onDelete('cascade');

            //Amount
            $table->float('amount', 8, 2);

            //Due Charges
            $table->float('due_charges', 8, 2)->nullable()->default(null);

            //date
            $table->date('date');

            //amount
            $table->float('amount', 8, 2)->change();

            //foreign key payment_transaction_id
            $table->bigInteger('payment_transaction_id')->unsigned()->index();
            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->onDelete('cascade');

            $table->timestamps();
        });

        // Making Payment Gateway , Order ID nullable and adding mode column
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->smallInteger('payment_gateway')->nullable(true)->change();
            $table->string('order_id')->nullable(true)->change();
            $table->smallInteger('mode')->comment('0 - cash 1 - cheque 2 - online')->default(2)->after('parent_id');
            $table->string('cheque_no')->nullable(true)->after('mode');
            $table->smallInteger('type_of_fee')->comment('0 - compulosry_full , 1 - installments , 2 -optional')->default(0)->after('cheque_no');
            $table->float('total_amount', 8, 2)->change();
            $table->dateTime('date')->nullable(true)->after('total_amount');
        });

        // Making mode nullable column and total amount to double
        Schema::table('fees_paids', function (Blueprint $table) {
            $table->smallInteger('mode')->comment('0 - cash 1 - cheque 2 - online')->nullable(true)->change();
            $table->float('total_amount', 8, 2)->change();
        });

        Schema::table('fees_choiceables', function (Blueprint $table) {
            $table->float('total_amount', 8, 2)->change();
            $table->date('date')->after('session_year_id')->nullable()->default(null);

            //foreign key payment_transaction_id
            $table->bigInteger('payment_transaction_id')->unsigned()->nullable()->default(null)->index()->after('date');
            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('paid_installment_fees');

        // Making Payment Gateway , Order ID nullable false and removing mode column
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->smallInteger('payment_gateway')->nullable(false)->change();
            $table->string('order_id')->nullable(false)->change();
            $table->dropColumn('mode');
            $table->dropColumn('cheque_no');
            $table->dropColumn('type_of_fee');
            $table->integer('total_amount')->change();
            $table->dropColumn('date');
        });

        // Making mode nullable false and total amount to integer
        Schema::table('fees_paids', function (Blueprint $table) {
            $table->smallInteger('mode')->comment('0 - cash 1 - cheque 2 - online')->nullable(false)->change();
            $table->integer('total_amount')->change();
        });

        //remove the date and changing back total amount to integer
        Schema::table('fees_choiceables', function (Blueprint $table) {
            $table->integer('total_amount')->change();
            $table->dropColumn('date');
            $table->dropColumn('payment_transaction_id');
        });
    }
};
