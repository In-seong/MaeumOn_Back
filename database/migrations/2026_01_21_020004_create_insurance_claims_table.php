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
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->comment('고객 FK');
            $table->foreignId('claim_form_template_id')
                  ->constrained('claim_form_templates')
                  ->cascadeOnDelete()
                  ->comment('사용한 양식 FK');
            $table->enum('status', ['pending', 'processing', 'completed', 'rejected'])
                  ->default('pending')
                  ->comment('청구 상태');
            $table->string('generated_image_path')->nullable()->comment('생성된 청구서 이미지 경로');
            $table->string('generated_pdf_path')->nullable()->comment('생성된 청구서 PDF 경로');
            $table->timestamp('fax_sent_at')->nullable()->comment('팩스 발송 시각');
            $table->enum('fax_status', ['pending', 'sent', 'failed'])->nullable()->comment('팩스 발송 상태');
            $table->text('notes')->nullable()->comment('비고');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
    }
};
