<?php

namespace Modules\Custom\AdSlots\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Custom\AdSlots\Support\AdSizeSettings;

/**
 * Slot-level size defaults for a placement key.
 *
 * @property string $slot_key
 * @property string $size_mode
 * @property string|null $aspect_desktop
 * @property string|null $aspect_mobile
 * @property int|null $width_px
 * @property int|null $height_px
 * @property int|null $max_width_px
 */
class AdSlotPlacement extends Model
{
    /**
     * @var string
     */
    protected $table = 'ad_slots_placements';

    /**
     * @var string
     */
    protected $primaryKey = 'slot_key';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slot_key',
        'size_mode',
        'aspect_desktop',
        'aspect_mobile',
        'width_px',
        'height_px',
        'max_width_px',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width_px' => 'integer',
            'height_px' => 'integer',
            'max_width_px' => 'integer',
        ];
    }

    /**
     * @return array{
     *     size_mode: string,
     *     aspect_desktop: ?string,
     *     aspect_mobile: ?string,
     *     width_px: ?int,
     *     height_px: ?int,
     *     max_width_px: ?int
     * }
     */
    public function toSizeArray(): array
    {
        return [
            'size_mode' => $this->size_mode ?: AdSizeSettings::MODE_RATIO,
            'aspect_desktop' => $this->aspect_desktop,
            'aspect_mobile' => $this->aspect_mobile,
            'width_px' => $this->width_px,
            'height_px' => $this->height_px,
            'max_width_px' => $this->max_width_px,
        ];
    }
}
