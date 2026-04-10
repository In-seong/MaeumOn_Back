<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_prediction', function (Blueprint $table) {
            $table->increments('prediction_id');
            $table->char('customer_id', 8);
            $table->string('prediction_type', 20)->comment('cardio/stroke/diabetes/kidney/mi/health_age');
            $table->date('checkup_date')->nullable()->comment('예측 기준 검진일자');

            // 공통 결과 필드 (예측 5종)
            $table->string('risk_grade', 2)->nullable()->comment('1=잘하고있어요 ~ 5=관리필요');
            $table->string('risk_ratio', 20)->nullable()->comment('3년 내 발생 확률 %');
            $table->string('average_age', 10)->nullable()->comment('나의 연령대');
            $table->string('average_ratio', 20)->nullable()->comment('유사집단 내 위치 (21/100)');

            // 건강나이 전용
            $table->string('health_age', 10)->nullable();
            $table->string('chronological_age', 10)->nullable();
            $table->text('change_after_text')->nullable();

            // 상세
            $table->longText('detail_list_json')->nullable()->comment('위험요인 분석 JSON');
            $table->longText('sub_detail_list_json')->nullable()->comment('처방 메시지 JSON');
            $table->longText('compare_list_json')->nullable()->comment('건강예측 비교 JSON');

            // 메타
            $table->longText('codef_raw_data')->nullable();
            $table->dateTime('predicted_at');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();

            $table->index(['customer_id', 'prediction_type'], 'idx_health_prediction_cust_type');
            $table->index('predicted_at', 'idx_health_prediction_predicted_at');
            $table->foreign('customer_id')->references('customer_id')->on('customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_prediction');
    }
};
