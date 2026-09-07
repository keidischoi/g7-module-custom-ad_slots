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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slot_key' => $this->slot_key,
            'type' => $this->type,
            'title' => $this->title,
            'image_url' => $this->image_url,
            'image_url_desktop' => $this->image_url_desktop,
            'image_url_mobile' => $this->image_url_mobile,
            'bg_color' => $this->bg_color,
            // Resolved helpers for Bunjang-style hero / responsive carousel
            'image_desktop' => $this->image_url_desktop ?: $this->image_url,
            'image_mobile' => $this->image_url_mobile ?: $this->image_url_desktop ?: $this->image_url,
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
