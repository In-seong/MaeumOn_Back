<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_checkup', function (Blueprint $table) {
            // 식별/메타
            $table->string('checkup_year', 4)->nullable()->after('checkup_date')->comment('검진년도 YYYY');
            $table->string('organization_name', 200)->nullable()->after('hospital_name')->comment('CODEF resOrganizationName');
            $table->text('opinion')->nullable()->after('organization_name')->comment('CODEF resOpinion (소견)');
            $table->string('judgement', 50)->nullable()->after('opinion')->comment('판정: 정A/정B/주의/의심 등');

            // 신체계측
            $table->string('height', 10)->nullable()->after('judgement');
            $table->string('weight', 10)->nullable();
            $table->string('waist', 10)->nullable();
            $table->string('bmi', 10)->nullable();
            $table->string('sight', 20)->nullable();
            $table->string('hearing', 20)->nullable();

            // 혈압/요/혈색소
            $table->string('blood_pressure', 20)->nullable()->comment('수축기/이완기 ex 120/80');
            $table->string('urinary_protein', 20)->nullable();
            $table->string('hemoglobin', 10)->nullable();

            // 혈당/지질
            $table->string('fasting_blood_sugar', 10)->nullable();
            $table->string('total_cholesterol', 10)->nullable();
            $table->string('hdl_cholesterol', 10)->nullable();
            $table->string('ldl_cholesterol', 10)->nullable();
            $table->string('triglyceride', 10)->nullable();

            // 신장/간 기능
            $table->string('serum_creatinine', 10)->nullable();
            $table->string('gfr', 10)->nullable();
            $table->string('ast', 10)->nullable();
            $table->string('alt', 10)->nullable();
            $table->string('y_gtp', 10)->nullable()->comment('감마지티피');

            // 기타
            $table->string('tb_chest_disease', 100)->nullable();
            $table->string('osteoporosis', 100)->nullable();

            // 문진 (resQuestionInfoList)
            $table->longText('question_info_json')->nullable()->comment('흡연/음주/질환력/신체활동');

            // CODEF 동기화 메타
            $table->boolean('codef_synced')->default(false)->after('follow_up_required');
            $table->dateTime('synced_at')->nullable();
            $table->longText('codef_raw_data')->nullable()->comment('원본 JSON (디버깅용)');

            $table->index(['customer_id', 'checkup_date'], 'idx_health_checkup_cust_date');
            $table->index(['customer_id', 'checkup_year'], 'idx_health_checkup_cust_year');
        });
    }

    public function down(): void
    {
        Schema::table('health_checkup', function (Blueprint $table) {
            $table->dropIndex('idx_health_checkup_cust_date');
            $table->dropIndex('idx_health_checkup_cust_year');
            $table->dropColumn([
                'checkup_year', 'organization_name', 'opinion', 'judgement',
                'height', 'weight', 'waist', 'bmi', 'sight', 'hearing',
                'blood_pressure', 'urinary_protein', 'hemoglobin',
                'fasting_blood_sugar', 'total_cholesterol', 'hdl_cholesterol',
                'ldl_cholesterol', 'triglyceride',
                'serum_creatinine', 'gfr', 'ast', 'alt', 'y_gtp',
                'tb_chest_disease', 'osteoporosis',
                'question_info_json',
                'codef_synced', 'synced_at', 'codef_raw_data',
            ]);
        });
    }
};
