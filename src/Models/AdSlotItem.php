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
 * @property string|null $link_url
 * @property string|null $html_content
 * @property string|null $script_src
 * @property int $sort_order
 * @property bool $is_active
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
        'shop.detail.top',
        'shop.cart.top',
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
        'link_url',
        'html_content',
        'script_src',
        'sort_order',
        'is_active',
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
