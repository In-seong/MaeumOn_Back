<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_company', function (Blueprint $table) {
            $table->string('fax_number', 20)->nullable()->after('contact_phone');
            $table->string('logo_path', 255)->nullable()->after('fax_number');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_company', function (Blueprint $table) {
            $table->dropColumn(['fax_number', 'logo_path']);
        });
    }
};
