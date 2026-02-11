<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // FK 제약조건 삭제 → 컬럼 nullable 변경 → FK 재생성
        DB::statement('ALTER TABLE insurance_claim DROP FOREIGN KEY fk_insurance_claim_insurance');
        DB::statement("ALTER TABLE insurance_claim MODIFY insurance_id int(11) NULL COMMENT '보험 ID'");
        DB::statement('ALTER TABLE insurance_claim ADD CONSTRAINT fk_insurance_claim_insurance FOREIGN KEY (insurance_id) REFERENCES insurance (insurance_id) ON UPDATE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE insurance_claim DROP FOREIGN KEY fk_insurance_claim_insurance');
        DB::statement("ALTER TABLE insurance_claim MODIFY insurance_id int(11) NOT NULL COMMENT '보험 ID'");
        DB::statement('ALTER TABLE insurance_claim ADD CONSTRAINT fk_insurance_claim_insurance FOREIGN KEY (insurance_id) REFERENCES insurance (insurance_id) ON UPDATE CASCADE');
    }
};
