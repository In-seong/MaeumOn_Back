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
        Schema::create('device_token', function (Blueprint $table) {
            $table->id('device_token_id');
            $table->integer('account_id')->comment('계정 ID');
            $table->string('device_uuid', 100)->comment('기기 고유 식별자');
            $table->string('token_hash', 255)->comment('디바이스 인증 토큰 해시');
            $table->string('device_name', 100)->nullable()->comment('기기명 (예: iPhone 15)');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('account_id')->on('account')->onDelete('cascade');
            $table->unique(['account_id', 'device_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_token');
    }
};
