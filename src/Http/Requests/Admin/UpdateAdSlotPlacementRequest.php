<?php

namespace Modules\Custom\AdSlots\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Custom\AdSlots\Support\AdSizeSettings;

class UpdateAdSlotPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'aspect_desktop', 'aspect_mobile',
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
            'size_mode' => ['required', 'string', Rule::in(AdSizeSettings::MODES)],
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
        ];
    }
}
