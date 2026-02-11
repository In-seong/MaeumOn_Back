<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_field_value', function (Blueprint $table) {
            $table->integer('claim_field_value_id', true);
            $table->integer('claim_id');
            $table->integer('form_field_id');
            $table->text('field_value')->nullable();
            $table->timestamps();

            $table->foreign('claim_id')->references('claim_id')->on('insurance_claim')->cascadeOnDelete();
            $table->foreign('form_field_id')->references('form_field_id')->on('form_field')->cascadeOnDelete();
            $table->unique(['claim_id', 'form_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_field_value');
    }
};
