<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slot-level size defaults (ratio vs fixed) per placement key.
     */
    public function up(): void
    {
        Schema::create('ad_slots_placements', function (Blueprint $table) {
            $table->string('slot_key', 64)->primary()->comment('슬롯 키 (home.top 등)');
            $table->string('size_mode', 16)->default('ratio')->comment('ratio|fixed');
            $table->string('aspect_desktop', 32)->nullable()->comment('데스크톱 비율 (예: 3/1)');
            $table->string('aspect_mobile', 32)->nullable()->comment('모바일 비율 (예: 2/1)');
            $table->unsignedInteger('width_px')->nullable()->comment('고정 너비 px (null=컨테이너 100%)');
            $table->unsignedInteger('height_px')->nullable()->comment('고정 높이 px');
            $table->unsignedInteger('max_width_px')->nullable()->comment('최대 너비 px');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('ad_slots_placements', function (Blueprint $table) {
                $table->comment('광고 슬롯 배치 기본 크기 설정');
            });
        }

        $heroKeys = ['home.top', 'global.top'];
        $slotKeys = [
            'home.top',
            'home.mid',
            'home.bottom',
            'shop.list.top',
            'shop.list.bottom',
            'shop.detail.top',
            'shop.detail.bottom',
            'shop.cart.top',
            'shop.cart.bottom',
            'board.popular.top',
            'board.popular.bottom',
            'board.index.top',
            'board.index.bottom',
            'board.show.top',
            'board.show.bottom',
            'board.form.top',
            'board.form.bottom',
            'board.boards.top',
            'board.boards.bottom',
            'mypage.top',
            'mypage.bottom',
            'maker_bids.top',
            'maker_bids.bottom',
            'share.top',
            'share.bottom',
            'page.top',
            'page.bottom',
            'global.top',
            'global.bottom',
        ];

        $now = now();
        $rows = [];
        foreach ($slotKeys as $slotKey) {
            $isHero = in_array($slotKey, $heroKeys, true);
            $rows[] = [
                'slot_key' => $slotKey,
                'size_mode' => 'ratio',
                'aspect_desktop' => $isHero ? '3/1' : null,
                'aspect_mobile' => $isHero ? '2/1' : null,
                'width_px' => null,
                'height_px' => null,
                'max_width_px' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('ad_slots_placements')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_slots_placements');
    }
};
