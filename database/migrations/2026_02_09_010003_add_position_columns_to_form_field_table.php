<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_field', function (Blueprint $table) {
            $table->integer('x_position')->default(0)->after('validation_rules');
            $table->integer('y_position')->default(0)->after('x_position');
            $table->integer('width')->default(200)->after('y_position');
            $table->integer('height')->default(30)->after('width');
            $table->integer('font_size')->default(14)->after('height');
            $table->string('font_color', 7)->default('#000000')->after('font_size');
            $table->string('placeholder', 255)->nullable()->after('font_color');
            $table->string('default_value', 255)->nullable()->after('placeholder');
        });
    }

    public function down(): void
    {
        Schema::table('form_field', function (Blueprint $table) {
            $table->dropColumn([
                'x_position', 'y_position', 'width', 'height',
                'font_size', 'font_color', 'placeholder', 'default_value'
            ]);
        });
    }
};
