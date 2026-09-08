<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-ad flag: block browser context menu on storefront images.
     */
    public function up(): void
    {
        Schema::table('ad_slots_items', function (Blueprint $table) {
            $table->boolean('prevent_right_click')
                ->default(false)
                ->after('is_active')
                ->comment('스토어프론트 이미지 우클릭(컨텍스트 메뉴) 방지');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_slots_items', function (Blueprint $table) {
            $table->dropColumn('prevent_right_click');
        });
    }
};
