<?php

namespace Modules\Custom\AdSlots\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Custom\AdSlots\Models\AdSlotItem;

class StoreAdSlotItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'title', 'image_url', 'image_url_desktop', 'image_url_mobile', 'bg_color',
            'link_url', 'html_content', 'script_src', 'starts_at', 'ends_at',
        ];
        $merge = [];
        foreach ($nullable as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $merge[$key] = null;
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }


    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slot_key' => ['required', 'string', 'max:64', Rule::in(AdSlotItem::SLOT_KEYS)],
            'type' => ['required', 'string', Rule::in(AdSlotItem::TYPES)],
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
            'prevent_right_click' => ['nullable', 'boolean'],
            'open_in_new_tab' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
