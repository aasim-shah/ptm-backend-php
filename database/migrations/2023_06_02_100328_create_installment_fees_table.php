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
        // making amount to float
        Schema::table('fees_classes', function (Blueprint $table) {
            $table->float('amount', 8, 2)->change();
        });

        // making adding Instllment fee status , due date and due charges for the fees
        Schema::table('session_years', function (Blueprint $table) {
            $table->tinyInteger('include_fee_installments')->comment('0 - no 1 - yes')->after('end_date')->default(0);
            $table->date('fee_due_date')->default(date('Y-m-d'))->after('include_fee_installments');
            $table->integer('fee_due_charges')->comment('in percentage (%)')->default(0)->after('fee_due_date');
        });

        //Column Fully paid fee status in Fees Paid
        Schema::table('fees_paids', function (Blueprint $table) {
            $table->tinyInteger('is_fully_paid')->comment('0 - no 1 - yes')->after('total_amount')->default(1);
        });

        // Installment Fee table
        Schema::create('installment_fees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('due_date');
            $table->integer('due_charges')->comment('in percentage (%)');

            //foreign key session year id
            $table->bigInteger('session_year_id')->unsigned()->index();
            $table->foreign('session_year_id')->references('id')->on('session_years')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //revert to integer amount
        Schema::table('fees_classes', function (Blueprint $table) {
            $table->integer('amount')->change();
        });

        //remove Instllment fee status , due date and due charges for the fees columns
        Schema::table('session_years', function (Blueprint $table) {
            $table->dropColumn('include_fee_installments');
            $table->dropColumn('fee_due_date');
            $table->dropColumn('fee_due_charges');
        });

        // remove fully paid status from fees paid
        Schema::table('fees_paids', function (Blueprint $table) {
            $table->dropColumn('is_fully_paid');
        });

        //remove the table
        Schema::dropIfExists('installment_fees');

    }
};
