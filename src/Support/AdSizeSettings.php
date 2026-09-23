<?php

namespace Modules\Custom\AdSlots\Support;

use Illuminate\Support\Facades\Schema;
use Modules\Custom\AdSlots\Models\AdSlotItem;
use Modules\Custom\AdSlots\Models\AdSlotPlacement;

/**
 * Resolve effective ad size: item override ?? slot default ?? built-in fallback.
 */
final class AdSizeSettings
{
    public const MODE_RATIO = 'ratio';

    public const MODE_FIXED = 'fixed';

    public const MODES = [self::MODE_RATIO, self::MODE_FIXED];

    /**
     * Built-in fallbacks matching pre-1.4.0 hero/stack behaviour.
     *
     * @return array{
     *     size_mode: string,
     *     aspect_desktop: ?string,
     *     aspect_mobile: ?string,
     *     width_px: ?int,
     *     height_px: ?int,
     *     max_width_px: ?int
     * }
     */
    public static function builtInFor(string $slotKey): array
    {
        $isHero = in_array($slotKey, ['home.top', 'global.top'], true);

        return [
            'size_mode' => self::MODE_RATIO,
            'aspect_desktop' => $isHero ? '3/1' : null,
            'aspect_mobile' => $isHero ? '2/1' : null,
            'width_px' => null,
            'height_px' => null,
            'max_width_px' => null,
        ];
    }

    /**
     * Normalize aspect strings like "3:1", "3/1", "3 / 1" → "3 / 1" for CSS.
     */
    public static function normalizeAspect(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*[\/:]\s*(\d+(?:\.\d+)?)\s*$/', $raw, $m)) {
            return $m[1].' / '.$m[2];
        }
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*$/', $raw, $m)) {
            return $m[1].' / 1';
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>|null  $override
     * @param  array<string, mixed>|null  $slot
     * @return array{
     *     mode: string,
     *     aspect_desktop: ?string,
     *     aspect_mobile: ?string,
     *     width_px: ?int,
     *     height_px: ?int,
     *     max_width_px: ?int
     * }
     */
    public static function resolve(?array $override, ?array $slot, string $slotKey): array
    {
        $builtin = self::builtInFor($slotKey);
        $override = $override ?? [];
        $slot = $slot ?? [];

        $pick = static function (string $key) use ($override, $slot, $builtin): mixed {
            if (array_key_exists($key, $override) && $override[$key] !== null && $override[$key] !== '') {
                return $override[$key];
            }
            if (array_key_exists($key, $slot) && $slot[$key] !== null && $slot[$key] !== '') {
                return $slot[$key];
            }

            return $builtin[$key] ?? null;
        };

        $mode = $pick('size_mode');
        if (! in_array($mode, self::MODES, true)) {
            $mode = self::MODE_RATIO;
        }

        $aspectDesktop = self::normalizeAspect($pick('aspect_desktop'));
        $aspectMobile = self::normalizeAspect($pick('aspect_mobile'));

        $toInt = static function (mixed $v): ?int {
            if ($v === null || $v === '') {
                return null;
            }

            return max(0, (int) $v);
        };

        return [
            'mode' => $mode,
            'aspect_desktop' => $aspectDesktop,
            'aspect_mobile' => $aspectMobile,
            'width_px' => $toInt($pick('width_px')),
            'height_px' => $toInt($pick('height_px')),
            'max_width_px' => $toInt($pick('max_width_px')),
        ];
    }

    /**
     * @return array{
     *     mode: string,
     *     aspect_desktop: ?string,
     *     aspect_mobile: ?string,
     *     width_px: ?int,
     *     height_px: ?int,
     *     max_width_px: ?int
     * }
     */
    public static function resolveForItem(AdSlotItem $item, ?AdSlotPlacement $placement = null): array
    {
        if ($placement === null && Schema::hasTable('ad_slots_placements')) {
            $placement = AdSlotPlacement::query()->find($item->slot_key);
        }

        return self::resolve(
            [
                'size_mode' => $item->size_mode,
                'aspect_desktop' => $item->aspect_desktop,
                'aspect_mobile' => $item->aspect_mobile,
                'width_px' => $item->width_px,
                'height_px' => $item->height_px,
                'max_width_px' => $item->max_width_px,
            ],
            $placement?->toSizeArray(),
            (string) $item->slot_key
        );
    }

    /**
     * Validation regex for aspect fields.
     */
    public static function aspectRule(): string
    {
        return 'nullable|string|max:32|regex:/^\s*\d+(\.\d+)?\s*[\/:]\s*\d+(\.\d+)?\s*$|^\s*\d+(\.\d+)?\s*$/';
    }
}
