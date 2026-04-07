<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 개발DB ↔ 운영DB 동기화: 누락 컬럼 3건 추가
     * - consultation.consultation_answer (상담 답변)
     * - insurance_claim.paid_date (보험금 지급일)
     * - insurance_claim.paid_amount (보험금 지급 금액)
     */
    public function up(): void
    {
        Schema::table('consultation', function (Blueprint $table) {
            $table->text('consultation_answer')->nullable()->after('consultation_content');
        });

        Schema::table('insurance_claim', function (Blueprint $table) {
            $table->date('paid_date')->nullable()->after('approval_date');
            $table->decimal('paid_amount', 12, 2)->nullable()->after('paid_date');
        });
    }

    public function down(): void
    {
        Schema::table('consultation', function (Blueprint $table) {
            $table->dropColumn('consultation_answer');
        });

        Schema::table('insurance_claim', function (Blueprint $table) {
            $table->dropColumn(['paid_date', 'paid_amount']);
        });
    }
};
