<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('claim_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_claim_id')
                  ->constrained('insurance_claims')
                  ->cascadeOnDelete()
                  ->comment('청구 FK');
            $table->foreignId('template_field_id')
                  ->constrained('template_fields')
                  ->cascadeOnDelete()
                  ->comment('필드 정의 FK');
            $table->text('field_value')->nullable()->comment('입력된 값');
            $table->timestamps();

            // 같은 청구 내에서 필드 중복 방지
            $table->unique(['insurance_claim_id', 'template_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_field_values');
    }
};
