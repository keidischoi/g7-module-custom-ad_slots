<?php

namespace Modules\Custom\AdSlots\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 광고 슬롯 아이템
 *
 * @property int $id
 * @property string $slot_key
 * @property string $type
 * @property string|null $title
 * @property string|null $image_url
 * @property string|null $image_url_desktop
 * @property string|null $image_url_mobile
 * @property string|null $bg_color
 * @property string|null $link_url
 * @property string|null $html_content
 * @property string|null $script_src
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $prevent_right_click
 * @property bool $open_in_new_tab
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 */
class AdSlotItem extends Model
{
    /**
     * 지원 슬롯 키 목록
     *
     * @var list<string>
     */
    public const SLOT_KEYS = [
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
        'global.top',
        'global.bottom',
    ];

    /**
     * @var list<string>
     */
    public const TYPES = [
        'static',
        'dynamic',
    ];

    /**
     * @var string
     */
    protected $table = 'ad_slots_items';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slot_key',
        'type',
        'title',
        'image_url',
        'image_url_desktop',
        'image_url_mobile',
        'bg_color',
        'link_url',
        'html_content',
        'script_src',
        'sort_order',
        'is_active',
        'prevent_right_click',
        'open_in_new_tab',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'prevent_right_click' => 'boolean',
            'open_in_new_tab' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * 활성 + 스케줄 윈도우 내 아이템만.
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }
}
