<?php

namespace Modules\Custom\AdSlots\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 관리자/공개 공통 광고 아이템 리소스 (Laravel JsonResource — G7 BaseApiResource 미의존).
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
        'global.top' => '전체 페이지 상단',
        'global.bottom' => '전체 페이지 하단',
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
            'starts_at' => optional($this->starts_at)?->toIso8601String(),
            'ends_at' => optional($this->ends_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
