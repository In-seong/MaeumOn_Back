<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_payment_history', function (Blueprint $table) {
            $table->increments('payment_id');
            $table->char('customer_id', 8)->index();
            $table->integer('insurance_id')->nullable()->index();
            $table->string('company_name', 100)->nullable();
            $table->string('policy_number', 50)->nullable();
            $table->string('insurance_name', 200)->nullable();
            $table->string('res_number', 20)->nullable();
            $table->string('occur_date_time', 20)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('payment_type', 50)->nullable();
            $table->string('reason', 200)->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('judge_result', 50)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();

            $table->foreign('customer_id')->references('customer_id')->on('customer');
            $table->foreign('insurance_id')->references('insurance_id')->on('insurance')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_payment_history');
    }
};
