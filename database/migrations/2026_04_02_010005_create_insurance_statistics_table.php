<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_statistics', function (Blueprint $table) {
            $table->increments('stat_id');
            $table->char('customer_id', 8)->index();
            $table->enum('stat_type', ['flat_rate', 'actual_loss']);
            $table->string('coverage_name', 200);
            $table->decimal('self_coverage_amt', 15, 2)->nullable();
            $table->decimal('avg_group_coverage_amt', 15, 2)->nullable();
            $table->string('self_reg_yn', 1)->nullable();
            $table->string('avg_group_reg_rate', 10)->nullable();
            $table->dateTime('synced_at');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();

            $table->foreign('customer_id')->references('customer_id')->on('customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_statistics');
    }
};
