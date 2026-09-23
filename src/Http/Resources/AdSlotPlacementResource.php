<?php

namespace Modules\Custom\AdSlots\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Custom\AdSlots\Support\AdSizeSettings;

/**
 * @mixin \Modules\Custom\AdSlots\Models\AdSlotPlacement
 */
class AdSlotPlacementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $labels = [
            'home.top' => '홈 상단',
            'home.mid' => '홈 중단',
            'home.bottom' => '홈 하단',
            'shop.list.top' => '쇼핑몰 목록 상단',
            'shop.list.bottom' => '쇼핑몰 목록 하단',
            'shop.detail.top' => '상품 상세 상단',
            'shop.detail.bottom' => '상품 상세 하단',
            'shop.cart.top' => '장바구니 상단',
            'shop.cart.bottom' => '장바구니 하단',
            'board.popular.top' => '인기글 상단',
            'board.popular.bottom' => '인기글 하단',
            'board.index.top' => '게시판 목록 상단',
            'board.index.bottom' => '게시판 목록 하단',
            'board.show.top' => '게시글 상세 상단',
            'board.show.bottom' => '게시글 상세 하단',
            'board.form.top' => '게시글 작성 상단',
            'board.form.bottom' => '게시글 작성 하단',
            'board.boards.top' => '게시판 목록(전체) 상단',
            'board.boards.bottom' => '게시판 목록(전체) 하단',
            'mypage.top' => '마이페이지 상단',
            'mypage.bottom' => '마이페이지 하단',
            'maker_bids.top' => '제작·입찰 상단',
            'maker_bids.bottom' => '제작·입찰 하단',
            'share.top' => '공유 상단',
            'share.bottom' => '공유 하단',
            'page.top' => '정적 페이지 상단',
            'page.bottom' => '정적 페이지 하단',
            'global.top' => '전체 · 상단',
            'global.bottom' => '전체 · 하단',
        ];

        return [
            'slot_key' => $this->slot_key,
            'slot_label' => $labels[$this->slot_key] ?? $this->slot_key,
            'size_mode' => $this->size_mode ?: AdSizeSettings::MODE_RATIO,
            'aspect_desktop' => $this->aspect_desktop,
            'aspect_mobile' => $this->aspect_mobile,
            'width_px' => $this->width_px,
            'height_px' => $this->height_px,
            'max_width_px' => $this->max_width_px,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
