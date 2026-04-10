<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_external_account', function (Blueprint $table) {
            $table->increments('health_external_account_id');
            $table->char('customer_id', 8)->unique();

            // 동의 상태 (NHIS 건강검진 + HIRA 진료정보)
            $table->dateTime('checkup_consent_at')->nullable()->comment('NHIS 건강검진 조회 동의 시각');
            $table->dateTime('medical_info_consent_at')->nullable()->comment('HIRA 내진료정보열람 동의 시각');

            // 건강iN 연동 상태 (건강나이/예측 API용 type=0/1)
            $table->boolean('health_in_linked')->default(false);
            $table->dateTime('health_in_linked_at')->nullable();

            // 마지막 동기화 시각 (API별)
            $table->dateTime('last_checkup_sync_at')->nullable()->comment('NHIS 건강검진결과');
            $table->dateTime('last_medical_info_sync_at')->nullable()->comment('HIRA 내진료정보열람');
            $table->dateTime('last_examination_sync_at')->nullable()->comment('NHIS 검진대상');
            $table->dateTime('last_health_age_sync_at')->nullable()->comment('NHIS 건강나이');
            $table->dateTime('last_prediction_sync_at')->nullable()->comment('NHIS 예측 5종 공통');

            // HIRA 동기화 구간 추적 (증분용)
            $table->date('medical_info_range_start')->nullable()->comment('HIRA 마지막 호출 startDate');
            $table->date('medical_info_range_end')->nullable()->comment('HIRA 마지막 호출 endDate');

            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();

            $table->foreign('customer_id')->references('customer_id')->on('customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_external_account');
    }
};
