<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_claim', function (Blueprint $table) {
            $table->string('fax_batch_id', 50)->nullable()->after('fax_status');
            $table->string('fax_number_sent', 20)->nullable()->after('fax_batch_id');
            $table->string('fax_result_code', 10)->nullable()->after('fax_number_sent');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_claim', function (Blueprint $table) {
            $table->dropColumn(['fax_batch_id', 'fax_number_sent', 'fax_result_code']);
        });
    }
};
