<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_page', function (Blueprint $table) {
            $table->string('page_image_path', 255)->nullable()->after('page_description');
            $table->integer('image_width')->nullable()->after('page_image_path');
            $table->integer('image_height')->nullable()->after('image_width');
        });
    }

    public function down(): void
    {
        Schema::table('form_page', function (Blueprint $table) {
            $table->dropColumn(['page_image_path', 'image_width', 'image_height']);
        });
    }
};
