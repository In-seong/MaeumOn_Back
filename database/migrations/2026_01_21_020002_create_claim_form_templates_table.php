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
        Schema::create('claim_form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_company_id')
                  ->constrained('insurance_companies')
                  ->cascadeOnDelete()
                  ->comment('보험사 FK');
            $table->string('name', 100)->comment('양식명');
            $table->text('description')->nullable()->comment('양식 설명');
            $table->string('template_image_path')->nullable()->comment('양식 이미지 파일 경로');
            $table->unsignedInteger('image_width')->nullable()->comment('이미지 너비 (px)');
            $table->unsignedInteger('image_height')->nullable()->comment('이미지 높이 (px)');
            $table->boolean('is_active')->default(true)->comment('활성화 여부');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_form_templates');
    }
};
