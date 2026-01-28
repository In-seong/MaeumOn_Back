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
        Schema::create('template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_form_template_id')
                  ->constrained('claim_form_templates')
                  ->cascadeOnDelete()
                  ->comment('양식 FK');
            $table->string('field_name', 50)->comment('필드명 (예: patient_name)');
            $table->string('field_label', 100)->comment('필드 라벨 (예: 환자명)');
            $table->enum('field_type', ['text', 'date', 'number', 'resident_number', 'phone', 'textarea'])
                  ->default('text')
                  ->comment('필드 타입');
            $table->unsignedInteger('x_position')->default(0)->comment('X 좌표 (px)');
            $table->unsignedInteger('y_position')->default(0)->comment('Y 좌표 (px)');
            $table->unsignedInteger('width')->default(200)->comment('필드 너비 (px)');
            $table->unsignedInteger('height')->default(30)->comment('필드 높이 (px)');
            $table->unsignedTinyInteger('font_size')->default(12)->comment('글꼴 크기');
            $table->string('font_color', 7)->default('#000000')->comment('글꼴 색상');
            $table->boolean('is_required')->default(false)->comment('필수 여부');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('정렬 순서');
            $table->string('placeholder')->nullable()->comment('플레이스홀더');
            $table->string('default_value')->nullable()->comment('기본값');
            $table->timestamps();

            // 같은 양식 내에서 필드명 중복 방지
            $table->unique(['claim_form_template_id', 'field_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_fields');
    }
};
