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
        Schema::create('insurance_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('보험사명');
            $table->string('code', 20)->unique()->comment('보험사 코드');
            $table->string('fax_number', 20)->nullable()->comment('기본 팩스번호');
            $table->string('logo_path')->nullable()->comment('로고 이미지 경로');
            $table->boolean('is_active')->default(true)->comment('활성화 여부');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_companies');
    }
};
