<?php

namespace Modules\Custom\AdSlots\Database\Seeders\Sample;

use Illuminate\Database\Seeder;
use Modules\Custom\AdSlots\Models\AdSlotItem;

/**
 * 샘플 정적 광고 시더
 *
 * php artisan module:seed custom-ad_slots --sample
 * (코어 시더 디스커버리에 따라 경로가 다를 수 있음 — README curl 예시 권장)
 */
class AdSlotSampleSeeder extends Seeder
{
    public function run(): void
    {
        AdSlotItem::query()->firstOrCreate(
            [
                'slot_key' => 'home.top',
                'title' => '샘플 홈 상단 배너',
            ],
            [
                'type' => 'static',
                'image_url' => 'https://via.placeholder.com/1200x200.png?text=Home+Top+Ad',
                'link_url' => 'https://example.com',
                'html_content' => null,
                'script_src' => null,
                'sort_order' => 0,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
            ]
        );
    }
}
