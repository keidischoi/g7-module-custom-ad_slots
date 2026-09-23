<?php

namespace Modules\Custom\AdSlots\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Modules\Custom\AdSlots\Models\AdSlotPlacement;
use Modules\Custom\AdSlots\Support\AdSizeSettings;

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $desktop = $this->image_url_desktop ?: $this->image_url;
        $mobile = $this->image_url_mobile ?: $this->image_url_desktop ?: $this->image_url;

        $placement = null;
        if ($this->resource->relationLoaded('placement')) {
            $placement = $this->placement;
        } elseif (! empty($this->additional['placement_map']) && isset($this->additional['placement_map'][$this->slot_key])) {
            $placement = $this->additional['placement_map'][$this->slot_key];
        }

        try {
            $size = AdSizeSettings::resolve(
                [
                    'size_mode' => $this->sizeAttr('size_mode'),
                    'aspect_desktop' => $this->sizeAttr('aspect_desktop'),
                    'aspect_mobile' => $this->sizeAttr('aspect_mobile'),
                    'width_px' => $this->sizeAttr('width_px'),
                    'height_px' => $this->sizeAttr('height_px'),
                    'max_width_px' => $this->sizeAttr('max_width_px'),
                ],
                $placement instanceof AdSlotPlacement ? $placement->toSizeArray() : (is_array($placement) ? $placement : null),
                (string) $this->slot_key
            );
        } catch (\Throwable) {
            $size = AdSizeSettings::resolve(null, null, (string) $this->slot_key);
        }


        $thumbAspect = null;
        $mode = is_array($size) ? ($size['mode'] ?? null) : null;
        if ($mode === 'ratio') {
            $thumbAspect = is_array($size) ? ($size['aspect_desktop'] ?? null) : null;
        } elseif ($mode === 'fixed') {
            $w = is_array($size) ? ($size['width_px'] ?? null) : null;
            $h = is_array($size) ? ($size['height_px'] ?? null) : null;
            if ($w && $h) {
                $thumbAspect = $w.' / '.$h;
            }
        }
        if (($thumbAspect === null || $thumbAspect === '') && method_exists(AdSizeSettings::class, 'normalizeAspect')) {
            $thumbAspect = AdSizeSettings::normalizeAspect($this->sizeAttr('aspect_desktop'));
        }
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
            'size_mode' => $this->sizeAttr('size_mode'),
            'aspect_desktop' => $this->sizeAttr('aspect_desktop'),
            'aspect_mobile' => $this->sizeAttr('aspect_mobile'),
            'width_px' => $this->sizeAttr('width_px'),
            'height_px' => $this->sizeAttr('height_px'),
            'max_width_px' => $this->sizeAttr('max_width_px'),
            'size' => $size,
            'thumb_aspect' => $thumbAspect,
            'starts_at' => optional($this->starts_at)?->toIso8601String(),
            'ends_at' => optional($this->ends_at)?->toIso8601String(),
            'starts_at_local' => $this->toDatetimeLocal($this->starts_at),
            'ends_at_local' => $this->toDatetimeLocal($this->ends_at),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }


    private function sizeAttr(string $key): mixed
    {
        static $hasSize = null;
        if ($hasSize === null) {
            try {
                $hasSize = \Illuminate\Support\Facades\Schema::hasColumn('ad_slots_items', 'size_mode');
            } catch (\Throwable) {
                $hasSize = false;
            }
        }
        if (! $hasSize) {
            return null;
        }

        return $this->{$key};
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
