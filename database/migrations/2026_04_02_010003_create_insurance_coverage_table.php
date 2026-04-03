<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_coverage', function (Blueprint $table) {
            $table->increments('coverage_id');
            $table->integer('insurance_id')->index();
            $table->string('insured_person', 50)->nullable();
            $table->string('coverage_name', 200);
            $table->decimal('coverage_amount', 15, 2)->nullable();
            $table->string('coverage_status', 20)->nullable();
            $table->string('agreement_type', 20)->nullable();
            $table->string('coverage_type', 20)->nullable();
            $table->string('object_info', 200)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();

            $table->foreign('insurance_id')->references('insurance_id')->on('insurance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_coverage');
    }
};
