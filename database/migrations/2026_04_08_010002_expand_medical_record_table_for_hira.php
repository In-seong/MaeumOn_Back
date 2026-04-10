<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_record', function (Blueprint $table) {
            // HIRA 추가 필드
            $table->string('department', 100)->nullable()->after('hospital_name')->comment('진단과');
            $table->integer('visit_days')->nullable()->comment('내원일수');
            $table->decimal('total_amount', 12, 2)->nullable()->comment('총 진료비');
            $table->decimal('public_charge', 12, 2)->nullable()->comment('공단부담금');
            $table->decimal('deductible_amt', 12, 2)->nullable()->comment('본인부담금');
            $table->string('hospital_code', 50)->nullable()->comment('HIRA 병원코드');

            // 세부진료/처방약 (JSON 배열)
            $table->longText('detail_treat_list_json')->nullable()->comment('HIRA resDetailTreatList');
            $table->longText('prescribe_drug_list_json')->nullable()->comment('HIRA resPrescribeDrugList');

            // 동기화 메타
            $table->boolean('codef_synced')->default(false);
            $table->dateTime('synced_at')->nullable();
            $table->string('source', 20)->default('manual')->comment('manual/codef_hira');
            $table->longText('codef_raw_data')->nullable()->comment('원본 JSON (디버깅용)');

            $table->index(['customer_id', 'treatment_date'], 'idx_medical_record_cust_treat');
            $table->index(['customer_id', 'source'], 'idx_medical_record_cust_source');
            $table->index(['customer_id', 'diagnosis_code'], 'idx_medical_record_cust_diagnosis');
        });
    }

    public function down(): void
    {
        Schema::table('medical_record', function (Blueprint $table) {
            $table->dropIndex('idx_medical_record_cust_treat');
            $table->dropIndex('idx_medical_record_cust_source');
            $table->dropIndex('idx_medical_record_cust_diagnosis');
            $table->dropColumn([
                'department', 'visit_days', 'total_amount', 'public_charge', 'deductible_amt', 'hospital_code',
                'detail_treat_list_json', 'prescribe_drug_list_json',
                'codef_synced', 'synced_at', 'source', 'codef_raw_data',
            ]);
        });
    }
};
