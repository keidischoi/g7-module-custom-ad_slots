<?php

namespace Modules\Custom\AdSlots\Support;

/**
 * Module-owned ad partial fragments (ported from feat theme layouts/partials/ads).
 * Used by Event Hook listener so official theme need not ship those files.
 *
 * v1.2.7+: page stacks use native layout iteration with **inlined** banner markup
 * (module `partial:` paths often do not resolve inside official theme). Hero
 * carousels (home.top / global.top) remain empty mounts filled by hero-carousel.js.
 */
final class AdPlacementFragments
{
    private static ?array $bannerItem = null;

    public static function bannerItem(): array
    {
        if (self::$bannerItem !== null) {
            return self::$bannerItem;
        }

        $path = dirname(__DIR__, 2).'/resources/layouts/partials/ads/_banner_list.json';
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            self::$bannerItem = [
                'type' => 'basic',
                'name' => 'Div',
                'props' => ['className' => 'w-full'],
                'children' => [],
            ];

            return self::$bannerItem;
        }

        unset($data['meta']);
        self::$bannerItem = $data;

        return self::$bannerItem;
    }

    /**
     * Native stacked-banner wrap (feat theme pattern): iteration + inlined banner item.
     *
     * @return array<string, mixed>
     */
    public static function iterWrap(string $wrapId, string $comment, string $dsId, string $className, ?string $ifExpr = null): array
    {
        $wrap = [
            'id' => $wrapId,
            'comment' => $comment,
            'type' => 'basic',
            'name' => 'Div',
            'props' => [
                'className' => $className,
                'id' => $wrapId,
            ],
            'iteration' => [
                'source' => '{{'.$dsId.'.data ?? '.$dsId.' ?? []}}',
                'item_var' => 'ad',
            ],
            'children' => [self::bannerItem()],
        ];

        if ($ifExpr !== null && $ifExpr !== '') {
            $wrap['if'] = $ifExpr;
        } else {
            $wrap['if'] = '{{(('.$dsId.'.data ?? '.$dsId.' ?? []).length > 0)}}';
        }

        return $wrap;
    }

    /**
     * Empty mount Div for API-driven JS render (data-cas-ad-slot).
     * Used for hero carousel slots only (home.top / global.top).
     *
     * @return array<string, mixed>
     */
    public static function mountWrap(string $wrapId, string $comment, string $dsId, string $slotKey, string $className): array
    {
        return [
            'id' => $wrapId,
            'comment' => $comment,
            'type' => 'basic',
            'name' => 'Div',
            'props' => [
                'className' => $className,
                'id' => $wrapId,
                'data-cas-ad-slot' => $slotKey,
            ],
            'children' => [],
        ];
    }

    /**
     * Hero-capable mount (home.top / global.top) — empty stub with hero markers.
     *
     * @return array<string, mixed>
     */
    public static function heroMountWrap(string $wrapId, string $comment, string $slotKey, string $className): array
    {
        return [
            'id' => $wrapId,
            'comment' => $comment,
            'type' => 'basic',
            'name' => 'Div',
            'props' => [
                'className' => $className,
                'id' => $wrapId,
                'data-cas-ad-slot' => $slotKey,
                'data-cas-hero' => '1',
                'data-cas-hero-slot' => $slotKey,
            ],
            'children' => [],
        ];
    }

    /**
     * ad_home_mid → home.mid ; ad_shop_detail_top → shop.detail.top
     */
    public static function dsIdToSlot(string $dsId): string
    {
        $s = preg_replace('/^ad_/', '', $dsId) ?? $dsId;

        return str_replace('_', '.', $s);
    }

    /**
     * Slot key → stable wrap id (ad_shop_list_top_wrap).
     */
    public static function wrapIdForSlot(string $slotKey): string
    {
        return 'ad_'.str_replace('.', '_', $slotKey).'_wrap';
    }

    /**
     * Slot key → data_source id (ad_shop_list_top).
     */
    public static function dsIdForSlot(string $slotKey): string
    {
        return 'ad_'.str_replace('.', '_', $slotKey);
    }

    /**
     * @return array<string, mixed>
     */
    public static function dataSource(string $id, string $slot, string $label): array
    {
        return [
            'id' => $id,
            'label_key' => $label,
            'type' => 'api',
            'endpoint' => '/api/modules/custom-ad_slots/placements',
            'method' => 'GET',
            'auto_fetch' => true,
            'auth_mode' => 'optional',
            'loading_strategy' => 'progressive',
            'params' => [
                'slot' => $slot,
            ],
            'fallback' => [
                'data' => [],
            ],
            'errorHandling' => [
                '404' => [
                    'handler' => 'suppress',
                    'comment' => '모듈 미설치 시 조용히 무시',
                ],
                '500' => [
                    'handler' => 'suppress',
                ],
            ],
            'comment' => 'custom-ad_slots Event Hook / extension',
        ];
    }
}
