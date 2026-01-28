<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. 템플릿 페이지 테이블 생성
        Schema::create('template_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_form_template_id')
                  ->constrained('claim_form_templates')
                  ->cascadeOnDelete()
                  ->comment('양식 FK');
            $table->unsignedSmallInteger('page_number')->default(1)->comment('페이지 번호');
            $table->string('page_image_path')->nullable()->comment('페이지 이미지 파일 경로');
            $table->unsignedInteger('image_width')->nullable()->comment('이미지 너비 (px)');
            $table->unsignedInteger('image_height')->nullable()->comment('이미지 높이 (px)');
            $table->timestamps();

            // 같은 양식 내에서 페이지 번호 중복 방지
            $table->unique(['claim_form_template_id', 'page_number']);
        });

        // 2. template_fields에 page_id 컬럼 추가
        Schema::table('template_fields', function (Blueprint $table) {
            $table->foreignId('template_page_id')
                  ->nullable()
                  ->after('claim_form_template_id')
                  ->comment('페이지 FK');
        });

        // 3. 기존 데이터 마이그레이션: claim_form_templates의 이미지 → template_pages
        $templates = DB::table('claim_form_templates')
            ->whereNotNull('template_image_path')
            ->get();

        foreach ($templates as $template) {
            // 기존 템플릿 이미지를 첫 번째 페이지로 생성
            $pageId = DB::table('template_pages')->insertGetId([
                'claim_form_template_id' => $template->id,
                'page_number' => 1,
                'page_image_path' => $template->template_image_path,
                'image_width' => $template->image_width,
                'image_height' => $template->image_height,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 해당 템플릿의 모든 필드를 첫 번째 페이지에 연결
            DB::table('template_fields')
                ->where('claim_form_template_id', $template->id)
                ->update(['template_page_id' => $pageId]);
        }

        // 4. Foreign key 제약조건 추가
        Schema::table('template_fields', function (Blueprint $table) {
            $table->foreign('template_page_id')
                  ->references('id')
                  ->on('template_pages')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Foreign key 제거
        Schema::table('template_fields', function (Blueprint $table) {
            $table->dropForeign(['template_page_id']);
            $table->dropColumn('template_page_id');
        });

        Schema::dropIfExists('template_pages');
    }
};
