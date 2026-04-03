<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit4u_account', function (Blueprint $table) {
            $table->increments('credit4u_account_id');
            $table->char('customer_id', 8)->unique();
            $table->string('credit4u_login_id', 100)->nullable();
            $table->enum('registration_status', ['consented', 'registered', 'needs_verify', 'temp_password'])->default('consented');
            $table->unsignedBigInteger('consent_template_id')->nullable()->index();
            $table->dateTime('last_synced_at')->nullable();
            $table->dateTime('consent_agreed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();

            $table->foreign('customer_id')->references('customer_id')->on('customer');
            $table->foreign('consent_template_id')->references('consent_template_id')->on('consent_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit4u_account');
    }
};
