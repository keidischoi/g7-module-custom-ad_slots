<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Bunjang-style hero banners: desktop/mobile images + optional letterbox bg.
     */
    public function up(): void
    {
        Schema::table('ad_slots_items', function (Blueprint $table) {
            $table->string('image_url_desktop', 2048)
                ->nullable()
                ->after('image_url')
                ->comment('데스크톱/히어로 이미지 URL (와이드 캐러셀 권장)');
            $table->string('image_url_mobile', 2048)
                ->nullable()
                ->after('image_url_desktop')
                ->comment('모바일 이미지 URL');
            $table->string('bg_color', 32)
                ->nullable()
                ->after('image_url_mobile')
                ->comment('배너 레터박스 배경색 (예: #f5f5f5)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_slots_items', function (Blueprint $table) {
            $table->dropColumn(['image_url_desktop', 'image_url_mobile', 'bg_color']);
        });
    }
};
