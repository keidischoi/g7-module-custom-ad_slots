<?php

namespace Modules\Custom\AdSlots\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Custom\AdSlots\Models\AdSlotItem;

class UpdateAdSlotItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slot_key' => ['sometimes', 'required', 'string', 'max:64', Rule::in(AdSlotItem::SLOT_KEYS)],
            'type' => ['sometimes', 'required', 'string', Rule::in(AdSlotItem::TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_url_desktop' => ['nullable', 'string', 'max:2048'],
            'image_url_mobile' => ['nullable', 'string', 'max:2048'],
            'bg_color' => ['nullable', 'string', 'max:32'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'html_content' => ['nullable', 'string'],
            'script_src' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
