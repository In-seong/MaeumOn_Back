<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_claim', function (Blueprint $table) {
            $table->string('fax_result_code', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('insurance_claim', function (Blueprint $table) {
            $table->string('fax_result_code', 10)->nullable()->change();
        });
    }
};
