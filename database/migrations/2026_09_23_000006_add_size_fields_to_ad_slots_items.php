<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-ad size overrides (null = inherit slot default).
     */
    public function up(): void
    {
        Schema::table('ad_slots_items', function (Blueprint $table) {
            $table->string('size_mode', 16)
                ->nullable()
                ->after('open_in_new_tab')
                ->comment('ratio|fixed (null=슬롯 기본값)');
            $table->string('aspect_desktop', 32)
                ->nullable()
                ->after('size_mode')
                ->comment('데스크톱 비율 오버라이드');
            $table->string('aspect_mobile', 32)
                ->nullable()
                ->after('aspect_desktop')
                ->comment('모바일 비율 오버라이드');
            $table->unsignedInteger('width_px')
                ->nullable()
                ->after('aspect_mobile')
                ->comment('고정 너비 px 오버라이드');
            $table->unsignedInteger('height_px')
                ->nullable()
                ->after('width_px')
                ->comment('고정 높이 px 오버라이드');
            $table->unsignedInteger('max_width_px')
                ->nullable()
                ->after('height_px')
                ->comment('최대 너비 px 오버라이드');
        });
    }

    public function down(): void
    {
        Schema::table('ad_slots_items', function (Blueprint $table) {
            $table->dropColumn([
                'size_mode',
                'aspect_desktop',
                'aspect_mobile',
                'width_px',
                'height_px',
                'max_width_px',
            ]);
        });
    }
};
