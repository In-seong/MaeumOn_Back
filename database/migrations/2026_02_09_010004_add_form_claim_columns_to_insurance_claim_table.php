<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_claim', function (Blueprint $table) {
            $table->integer('claim_form_id')->nullable()->after('company_id');
            $table->string('generated_pdf_path', 255)->nullable()->after('rejection_reason');
            $table->dateTime('fax_sent_at')->nullable()->after('generated_pdf_path');
            $table->string('fax_status', 20)->nullable()->after('fax_sent_at');
            $table->text('notes')->nullable()->after('fax_status');
            $table->foreign('claim_form_id')->references('claim_form_id')->on('claim_form')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('insurance_claim', function (Blueprint $table) {
            $table->dropForeign(['claim_form_id']);
            $table->dropColumn(['claim_form_id', 'generated_pdf_path', 'fax_sent_at', 'fax_status', 'notes']);
        });
    }
};
