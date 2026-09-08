<?php

namespace Modules\Custom\AdSlots\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * 관리자/공개 공통 광고 아이템 리소스.
 *
 * @mixin \Modules\Custom\AdSlots\Models\AdSlotItem
 */
class AdSlotItemResource extends JsonResource
{
    /**
     * @var array<string, string>
     */
    private const SLOT_LABELS = [
        'home.top' => '홈 상단',
        'home.mid' => '홈 중단',
        'home.bottom' => '홈 하단',
        'shop.list.top' => '쇼핑몰 목록 상단',
        'shop.detail.top' => '상품 상세 상단',
        'shop.cart.top' => '장바구니 상단',
        'board.popular.top' => '인기글 상단',
        'global.top' => '전체 · 상단',
        'global.bottom' => '전체 · 하단',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $desktop = $this->image_url_desktop ?: $this->image_url;
        $mobile = $this->image_url_mobile ?: $this->image_url_desktop ?: $this->image_url;

        return [
            'id' => $this->id,
            'slot_key' => $this->slot_key,
            'slot_label' => self::SLOT_LABELS[$this->slot_key] ?? $this->slot_key,
            'type' => $this->type,
            'type_label' => $this->type === 'dynamic' ? '동적' : '정적',
            'title' => $this->title,
            'image_url' => $this->image_url,
            'image_url_desktop' => $this->image_url_desktop,
            'image_url_mobile' => $this->image_url_mobile,
            'bg_color' => $this->bg_color,
            'image_desktop' => $desktop,
            'image_mobile' => $mobile,
            'thumb_url' => $desktop ?: $mobile,
            'link_url' => $this->link_url,
            'html_content' => $this->html_content,
            'script_src' => $this->script_src,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'prevent_right_click' => (bool) $this->prevent_right_click,
            'open_in_new_tab' => (bool) ($this->open_in_new_tab ?? true),
            'starts_at' => optional($this->starts_at)?->toIso8601String(),
            'ends_at' => optional($this->ends_at)?->toIso8601String(),
            'starts_at_local' => $this->toDatetimeLocal($this->starts_at),
            'ends_at_local' => $this->toDatetimeLocal($this->ends_at),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * datetime-local input 값 (Asia/Seoul, 분 단위).
     */
    private function toDatetimeLocal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $dt = Carbon::parse($value)->timezone('Asia/Seoul');
        } catch (\Throwable) {
            return null;
        }

        return $dt->format('Y-m-d') . 'T' . $dt->format('H:i');
    }
}
