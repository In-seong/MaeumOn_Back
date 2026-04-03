<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance', function (Blueprint $table) {
            $table->string('contract_type', 20)->nullable()->after('expiration_date');
            $table->string('contractor_name', 50)->nullable()->after('contract_type');
            $table->string('contract_status', 20)->nullable()->after('contractor_name');
            $table->string('company_phone', 20)->nullable()->after('contract_status');
            $table->string('company_homepage', 255)->nullable()->after('company_phone');
            $table->string('payment_cycle', 20)->nullable()->after('company_homepage');
            $table->date('start_date')->nullable()->after('payment_cycle');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('insured_person', 50)->nullable()->after('end_date');
            $table->string('res_number', 20)->nullable()->after('insured_person');
            $table->date('contract_date_of')->nullable()->after('res_number');
            $table->string('car_name', 100)->nullable()->after('contract_date_of');
            $table->string('car_number', 20)->nullable()->after('car_name');
            $table->boolean('codef_synced')->default(false)->after('car_number');
            $table->json('codef_raw_data')->nullable()->after('codef_synced');
            $table->dateTime('synced_at')->nullable()->after('codef_raw_data');
        });
    }

    public function down(): void
    {
        Schema::table('insurance', function (Blueprint $table) {
            $table->dropColumn([
                'contract_type', 'contractor_name', 'contract_status',
                'company_phone', 'company_homepage', 'payment_cycle',
                'start_date', 'end_date', 'insured_person', 'res_number',
                'contract_date_of', 'car_name', 'car_number',
                'codef_synced', 'codef_raw_data', 'synced_at',
            ]);
        });
    }
};
