<?php

namespace Modules\Custom\AdSlots\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Custom\AdSlots\Models\AdSlotItem;
use Modules\Custom\AdSlots\Support\AdSizeSettings;

class UpdateAdSlotItemRequest extends FormRequest
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
            'size_mode', 'aspect_desktop', 'aspect_mobile',
            'width_px', 'height_px', 'max_width_px',
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
            'prevent_right_click' => ['nullable', 'boolean'],
            'open_in_new_tab' => ['nullable', 'boolean'],
            'size_mode' => ['nullable', 'string', Rule::in(AdSizeSettings::MODES)],
            'aspect_desktop' => ['nullable', 'string', 'max:32', 'regex:/^\s*\d+(\.\d+)?\s*[\/:]\s*\d+(\.\d+)?\s*$|^\s*\d+(\.\d+)?\s*$/'],
            'aspect_mobile' => ['nullable', 'string', 'max:32', 'regex:/^\s*\d+(\.\d+)?\s*[\/:]\s*\d+(\.\d+)?\s*$|^\s*\d+(\.\d+)?\s*$/'],
            'width_px' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'height_px' => [
                'nullable',
                'integer',
                'min:1',
                'max:10000',
                Rule::requiredIf(fn () => $this->input('size_mode') === AdSizeSettings::MODE_FIXED),
            ],
            'max_width_px' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'upload_token' => ['nullable', 'string', 'max:128'],
        ];
    }
}
