<?php

namespace Modules\Custom\AdSlots\Support;

/**
 * Module-owned ad partial fragments (ported from feat theme layouts/partials/ads).
 * Used by Event Hook listener so official theme need not ship those files.
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
     * @return array<string, mixed>
     */
    public static function iterWrap(string $wrapId, string $comment, string $dsId, string $className): array
    {
        return [
            'id' => $wrapId,
            'comment' => $comment,
            'type' => 'basic',
            'name' => 'Div',
            'if' => '{{(('.$dsId.'.data ?? '.$dsId.' ?? []).length > 0)}}',
            'props' => [
                'className' => $className,
            ],
            'iteration' => [
                'source' => '{{('.$dsId.'.data ?? '.$dsId.' ?? [])}}',
                'item_var' => 'ad',
            ],
            'children' => [self::bannerItem()],
        ];
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
