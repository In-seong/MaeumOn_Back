<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 과업지시서 요구사항 대비 누락 컬럼 추가
 * - customer.telecom (SFR-021, SFR-034: 통신사)
 * - customer.acquisition_channel (SFR-021, SFR-034: 고객정보취득경로)
 * - customer.last_contact_date (SFR-026: 최근 연락일)
 * - agent.specialization (SFR-042: 전문분야)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->string('telecom', 20)->nullable()->after('job')
                ->comment('통신사');
            $table->string('acquisition_channel', 50)->nullable()->after('telecom')
                ->comment('고객정보취득경로(병원 등)');
            $table->date('last_contact_date')->nullable()->after('acquisition_channel')
                ->comment('최근 연락일(Push/문자 발송일 기준)');
        });

        Schema::table('agent', function (Blueprint $table) {
            $table->string('specialization', 200)->nullable()->after('office_location')
                ->comment('전문분야');
        });
    }

    public function down(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropColumn(['telecom', 'acquisition_channel', 'last_contact_date']);
        });

        Schema::table('agent', function (Blueprint $table) {
            $table->dropColumn('specialization');
        });
    }
};
