<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 기존 동일 type이 있으면 건너뛰기 (idempotent)
        $existing = DB::table('consent_template')
            ->whereIn('consent_type', ['health_checkup', 'medical_info'])
            ->pluck('consent_type')
            ->toArray();

        $rows = [];

        if (!in_array('health_checkup', $existing)) {
            $rows[] = [
                'consent_type' => 'health_checkup',
                'title' => '건강검진정보 조회 동의',
                'content' => "본인은 보험ON이 국민건강보험공단(NHIS)이 제공하는 다음 정보를 조회·저장·이용하는 것에 동의합니다.\n\n[수집·이용 항목]\n- 건강검진 결과 (혈압, 혈당, 콜레스테롤 등 신체계측 및 검사 수치)\n- 검진대상 여부 (일반/암검진)\n- 건강나이 및 위험요인\n- 질환 예측 정보 (심뇌혈관, 뇌졸중, 당뇨, 만성신장, 심근경색)\n- 검진 소견 및 문진 정보\n\n[수집·이용 목적]\n- 건강 상태 진단 및 위험지표 알림\n- 보험금 청구 지원\n- 맞춤형 건강 관리 가이드 제공\n\n[보유·이용 기간]\n- 동의 철회 시 또는 회원 탈퇴 시까지\n\n동의를 거부할 권리가 있으며, 거부 시 건강 관련 서비스 이용이 제한됩니다.",
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!in_array('medical_info', $existing)) {
            $rows[] = [
                'consent_type' => 'medical_info',
                'title' => '내진료정보 조회 동의',
                'content' => "본인은 보험ON이 건강보험심사평가원(HIRA)이 제공하는 내진료정보열람 서비스를 통해 다음 정보를 조회·저장·이용하는 것에 동의합니다.\n\n[수집·이용 항목]\n- 최근 5년 진료내역 (진료일, 병원명, 진단과)\n- 주상병 코드 및 주상병명\n- 총 진료비, 공단부담금, 본인부담금\n- 처방 약품명·성분·투약 정보\n- 세부 처치/검사 내역\n\n※ 민감상병 정보(정신질환, 감염질환 등)가 포함될 수 있음을 고지드리며, 동의 시 이를 함께 수집합니다.\n\n[수집·이용 목적]\n- 보험금 청구 지원 (미청구 보험금 탐지)\n- 알릴의무 자동 분류 및 알림\n- 과거 병력 기반 건강 관리 가이드\n\n[보유·이용 기간]\n- 동의 철회 시 또는 회원 탈퇴 시까지\n\n[제3자 제공]\n- 본인 동의 없이 제3자에게 제공하지 않습니다.\n\n동의를 거부할 권리가 있으며, 거부 시 진료내역 기반 서비스 이용이 제한됩니다.",
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('consent_template')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('consent_template')
            ->whereIn('consent_type', ['health_checkup', 'medical_info'])
            ->delete();
    }
};
