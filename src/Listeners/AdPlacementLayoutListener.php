<?php

namespace Modules\Custom\AdSlots\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Custom\AdSlots\Support\AdPlacementFragments;

/**
 * Inject empty data-cas-ad-slot mounts into the live layout content tree for every
 * page that should show ads. Official shop/board/mypage layouts have no reliable
 * main_content id for overlay targeting — only slots.content[0] (or composed
 * main_content after merge). This listener owns page top/bottom mount insertion.
 *
 * global.top / global.bottom remain on _user_base overlay (always-on mounts + script).
 * home.mid is inserted between official home row1 and row2.
 * shop.detail.top is placed after the back button when possible.
 *
 * Ads only — no menu/search/icon/home-design UI.
 */
class AdPlacementLayoutListener implements HookListenerInterface
{
    private const HOME = 'home';

    private const SHOP_SHOW = 'shop/show';

    private const MID_WRAP_ID = 'ad_home_mid_wrap';

    private const DETAIL_TOP_WRAP_ID = 'ad_shop_detail_top_wrap';

    /**
     * Exact layout_name → [topSlot, bottomSlot] (null = skip that side).
     * home.mid handled separately. mypage/* matched by prefix.
     *
     * @var array<string, array{0:?string,1:?string}>
     */
    private const LAYOUT_SLOTS = [
        'home' => ['home.top', 'home.bottom'],
        'shop/index' => ['shop.list.top', 'shop.list.bottom'],
        'shop/show' => ['shop.detail.top', 'shop.detail.bottom'],
        'shop/cart' => ['shop.cart.top', 'shop.cart.bottom'],
        'board/popular' => ['board.popular.top', 'board.popular.bottom'],
        'board/index' => ['board.index.top', 'board.index.bottom'],
        'board/show' => ['board.show.top', 'board.show.bottom'],
        'board/form' => ['board.form.top', 'board.form.bottom'],
        'board/boards' => ['board.boards.top', 'board.boards.bottom'],
    ];

    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout.filter_child_data' => [
                'method' => 'filterChildLayout',
                'priority' => 40,
                'type' => 'filter',
                'sync' => true,
            ],
            'core.layout.filter_merged' => [
                'method' => 'filterMergedLayout',
                'priority' => 40,
                'type' => 'filter',
                'sync' => true,
            ],
            'core.layout_extension.after_apply' => [
                'method' => 'afterExtensions',
                'priority' => 40,
                'type' => 'filter',
                'sync' => true,
            ],
        ];
    }

    public function handle(...$args): void
    {
    }

    public function filterChildLayout(mixed $childLayout = null, mixed $parentLayout = null): mixed
    {
        if (! is_array($childLayout)) {
            return $childLayout;
        }

        return $this->apply($childLayout);
    }

    public function filterMergedLayout(mixed $merged = null, mixed $parentLayout = null, mixed $childLayout = null): mixed
    {
        if (! is_array($merged)) {
            return $merged;
        }
        if (empty($merged['layout_name']) && is_array($childLayout) && ! empty($childLayout['layout_name'])) {
            $merged['layout_name'] = $childLayout['layout_name'];
        }

        return $this->apply($merged);
    }

    public function afterExtensions(mixed $layout = null, mixed $templateId = null): mixed
    {
        if (! is_array($layout)) {
            return $layout;
        }

        return $this->apply($layout);
    }

    private function apply(array $layout): array
    {
        $name = $this->resolveLayoutName($layout);
        if ($name === '' || $name === '_user_base') {
            return $layout;
        }

        // Never inject into checkout / order-complete flows (blank-page risk).
        if ($this->isExcludedLayout($name)) {
            return $layout;
        }

        $pair = $this->slotsForLayout($name);
        if ($pair !== null) {
            [$topSlot, $bottomSlot] = $pair;
            if ($topSlot !== null) {
                $layout = $this->ensurePageMount($layout, $topSlot, 'top', $name);
            }
            if ($bottomSlot !== null) {
                $layout = $this->ensurePageMount($layout, $bottomSlot, 'bottom', $name);
            }
        }

        if ($name === self::HOME) {
            $layout = $this->ensureDataSource($layout, 'ad_home_mid', 'home.mid', 'Ad home.mid');
            $layout = $this->insertHomeMid($layout);
        }

        if ($name === self::SHOP_SHOW) {
            $layout = $this->repositionShopDetailTop($layout);
        }

        return $layout;
    }

    private function resolveLayoutName(array $layout): string
    {
        $name = (string) ($layout['layout_name'] ?? $layout['name'] ?? '');
        // Strip module prefix if present (e.g. custom-foo.home)
        if ($name !== '' && ! str_contains($name, '/') && str_contains($name, '.')) {
            // Keep dotted board-like names only when they look prefixed
            // layout_name is usually "home", "shop/index", "mypage/profile"
        }

        return $name;
    }

    /**
     * Checkout / order layouts must never receive ad mounts.
     */
    private function isExcludedLayout(string $name): bool
    {
        $lower = strtolower($name);
        if ($lower === '') {
            return false;
        }

        $exact = [
            'shop/checkout',
            'checkout',
            'order_complete',
            'guest_order_show',
        ];
        if (in_array($lower, $exact, true)) {
            return true;
        }

        // Any layout path/name containing "checkout"
        if (str_contains($lower, 'checkout')) {
            return true;
        }

        return false;
    }

    /**
     * @return array{0:?string,1:?string}|null
     */
    private function slotsForLayout(string $name): ?array
    {
        if (isset(self::LAYOUT_SLOTS[$name])) {
            return self::LAYOUT_SLOTS[$name];
        }

        // mypage, mypage/profile, mypage/orders/show, …
        if ($name === 'mypage' || str_starts_with($name, 'mypage/')) {
            return ['mypage.top', 'mypage.bottom'];
        }

        return null;
    }

    /**
     * Ensure a top (prepend) or bottom (append) mount exists in the content tree.
     */
    private function ensurePageMount(array $layout, string $slotKey, string $side, string $layoutName): array
    {
        $wrapId = AdPlacementFragments::wrapIdForSlot($slotKey);
        if ($this->treeHasId($layout, $wrapId)) {
            return $layout;
        }

        $dsId = AdPlacementFragments::dsIdForSlot($slotKey);
        $layout = $this->ensureDataSource($layout, $dsId, $slotKey, 'Ad '.$slotKey);

        $isHero = in_array($slotKey, ['home.top', 'global.top'], true);
        $className = $side === 'top'
            ? ($isHero
                ? 'relative w-full overflow-hidden rounded-xl mb-4'
                : 'mb-4 flex flex-col gap-3')
            : 'mt-4 flex flex-col gap-3';

        $comment = sprintf(
            '=== Ad slot: %s (%s %s) — API mount ===',
            $slotKey,
            $layoutName,
            $side
        );

        $wrap = $isHero
            ? AdPlacementFragments::heroMountWrap($wrapId, $comment, $slotKey, $className)
            : AdPlacementFragments::mountWrap($wrapId, $comment, $dsId, $slotKey, $className);

        // shop.detail.top: prefer after back button (repositionShopDetailTop finalizes)
        if ($slotKey === 'shop.detail.top') {
            $done = false;
            if (isset($layout['slots']) && is_array($layout['slots'])) {
                $layout['slots'] = $this->prependOrAppendInContent($layout['slots'], $wrap, 'top', $done);
            }
            if (! $done && isset($layout['components']) && is_array($layout['components'])) {
                $layout['components'] = $this->prependOrAppendInContent($layout['components'], $wrap, 'top', $done);
            }

            return $layout;
        }

        $done = false;
        if (isset($layout['slots']) && is_array($layout['slots'])) {
            $layout['slots'] = $this->prependOrAppendInContent($layout['slots'], $wrap, $side, $done);
        }
        if (! $done && isset($layout['components']) && is_array($layout['components'])) {
            $layout['components'] = $this->prependOrAppendInContent($layout['components'], $wrap, $side, $done);
        }

        return $layout;
    }

    /**
     * Find main_content OR slots.content[0] / first content Container and prepend/append.
     *
     * @param  array<mixed>  $node
     * @param  array<string, mixed>  $wrap
     * @return array<mixed>
     */
    private function prependOrAppendInContent(array $node, array $wrap, string $side, bool &$done): array
    {
        if ($done) {
            return $node;
        }

        // Prefer explicit main_content (composed / _user_base slot host)
        if (($node['id'] ?? '') === 'main_content' && isset($node['children']) && is_array($node['children'])) {
            $children = $node['children'];
            if ($side === 'top') {
                array_unshift($children, $wrap);
            } else {
                $children[] = $wrap;
            }
            $node['children'] = $children;
            $done = true;

            return $node;
        }

        // slots.content[0] pattern (official page layouts)
        if (isset($node['content']) && is_array($node['content']) && $this->isList($node['content'])) {
            $content = $node['content'];
            if (isset($content[0]) && is_array($content[0])) {
                $host = $content[0];
                if (isset($host['children']) && is_array($host['children'])) {
                    $children = $host['children'];
                    if ($side === 'top') {
                        array_unshift($children, $wrap);
                    } else {
                        $children[] = $wrap;
                    }
                    $host['children'] = $children;
                    $content[0] = $host;
                    $node['content'] = $content;
                    $done = true;

                    return $node;
                }
                // content[0] has no children — wrap as sibling list
                if ($side === 'top') {
                    array_unshift($content, $wrap);
                } else {
                    $content[] = $wrap;
                }
                $node['content'] = $content;
                $done = true;

                return $node;
            }
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->prependOrAppendInContent($value, $wrap, $side, $done);
                if ($done) {
                    return $node;
                }
            }
        }

        return $node;
    }

    private function ensureDataSource(array $layout, string $id, string $slot, string $label): array
    {
        $sources = $layout['data_sources'] ?? [];
        if (! is_array($sources)) {
            $sources = [];
        }
        foreach ($sources as $ds) {
            if (is_array($ds) && ($ds['id'] ?? '') === $id) {
                $layout['data_sources'] = $sources;

                return $layout;
            }
        }
        $sources[] = AdPlacementFragments::dataSource($id, $slot, $label);
        $layout['data_sources'] = $sources;

        return $layout;
    }

    private function insertHomeMid(array $layout): array
    {
        if ($this->treeHasId($layout, self::MID_WRAP_ID)) {
            return $layout;
        }

        $wrap = AdPlacementFragments::mountWrap(
            self::MID_WRAP_ID,
            '=== Ad slot: home.mid (1행과 2행 사이) — API mount ===',
            'ad_home_mid',
            'home.mid',
            'mb-4 flex flex-col gap-3'
        );

        $done = false;
        if (isset($layout['slots']) && is_array($layout['slots'])) {
            $layout['slots'] = $this->insertMidIntoHomeSlots($layout['slots'], $wrap, $done);
        }
        if (! $done && isset($layout['components']) && is_array($layout['components'])) {
            $layout['components'] = $this->insertMidIntoHomeSlots($layout['components'], $wrap, $done);
        }

        return $layout;
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<string, mixed>  $wrap
     * @return array<mixed>
     */
    private function insertMidIntoHomeSlots(array $node, array $wrap, bool &$done): array
    {
        if ($done) {
            return $node;
        }

        if ($this->looksLikeHomeOuterContainer($node)) {
            $children = $node['children'] ?? [];
            if (is_array($children) && count($children) >= 2) {
                $insertAt = $this->indexAfterFirstContentRow($children);
                array_splice($children, $insertAt, 0, [$wrap]);
                $node['children'] = $children;
                $done = true;

                return $node;
            }
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->insertMidIntoHomeSlots($value, $wrap, $done);
                if ($done) {
                    return $node;
                }
            }
        }

        return $node;
    }

    /**
     * @param  array<mixed>  $node
     */
    private function looksLikeHomeOuterContainer(array $node): bool
    {
        if (($node['name'] ?? '') !== 'Container') {
            return false;
        }
        $children = $node['children'] ?? null;
        if (! is_array($children) || count($children) < 2) {
            return false;
        }
        $comments = [];
        foreach ($children as $child) {
            if (is_array($child)) {
                $comments[] = (string) ($child['comment'] ?? '');
            }
        }
        $joined = implode("\n", $comments);

        return str_contains($joined, '1행') || str_contains($joined, 'Welcome')
            || str_contains($joined, '2행') || str_contains($joined, '최근 게시글');
    }

    /**
     * @param  array<int, mixed>  $children
     */
    private function indexAfterFirstContentRow(array $children): int
    {
        foreach ($children as $i => $child) {
            if (! is_array($child)) {
                continue;
            }
            $comment = (string) ($child['comment'] ?? '');
            $id = (string) ($child['id'] ?? '');
            if ($id === 'ad_home_top_wrap' || str_contains($comment, 'home.top')) {
                continue;
            }
            if (str_contains($comment, '1행') || str_contains($comment, 'Welcome') || $comment !== '') {
                return $i + 1;
            }
        }

        return min(1, count($children));
    }

    /**
     * Move shop.detail.top wrap to immediately after the first child (back button).
     */
    private function repositionShopDetailTop(array $layout): array
    {
        $section = null;
        if (isset($layout['components']) && is_array($layout['components'])) {
            $layout['components'] = $this->extractById($layout['components'], self::DETAIL_TOP_WRAP_ID, $section);
        }
        if ($section === null && isset($layout['slots']) && is_array($layout['slots'])) {
            $layout['slots'] = $this->extractById($layout['slots'], self::DETAIL_TOP_WRAP_ID, $section);
        }
        if ($section === null) {
            $section = AdPlacementFragments::mountWrap(
                self::DETAIL_TOP_WRAP_ID,
                'Ad slot: shop.detail.top (헤더/뒤로가기 다음) — API mount',
                'ad_shop_detail_top',
                'shop.detail.top',
                'mb-4 flex flex-col gap-3'
            );
        }

        $done = false;
        if (isset($layout['components']) && is_array($layout['components'])) {
            $layout['components'] = $this->insertAfterFirstContentChild($layout['components'], $section, $done);
        }
        if (! $done && isset($layout['slots']) && is_array($layout['slots'])) {
            $layout['slots'] = $this->insertAfterFirstContentChild($layout['slots'], $section, $done);
        }

        return $layout;
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<string, mixed>  $section
     * @return array<mixed>
     */
    private function insertAfterFirstContentChild(array $node, array $section, bool &$done): array
    {
        if ($done) {
            return $node;
        }

        if (($node['name'] ?? '') === 'Container' && isset($node['children']) && is_array($node['children'])) {
            $children = $node['children'];
            $first = $children[0] ?? null;
            if (is_array($first) && (($first['name'] ?? '') === 'Button' || str_contains((string) ($first['comment'] ?? ''), '뒤로'))) {
                if (is_array($children[0] ?? null) && ($children[0]['id'] ?? '') === self::DETAIL_TOP_WRAP_ID) {
                    array_shift($children);
                    $first = $children[0] ?? null;
                }
                $insertAt = 1;
                if (is_array($first) && ($first['id'] ?? '') === self::DETAIL_TOP_WRAP_ID) {
                    $insertAt = 2;
                }
                array_splice($children, $insertAt, 0, [$section]);
                $node['children'] = $children;
                $done = true;

                return $node;
            }
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->insertAfterFirstContentChild($value, $section, $done);
                if ($done) {
                    return $node;
                }
            }
        }

        return $node;
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<string, mixed>|null  $extracted
     * @return array<mixed>
     */
    private function extractById(array $node, string $id, ?array &$extracted): array
    {
        $isList = $this->isList($node);
        if ($isList) {
            $out = [];
            foreach ($node as $child) {
                if (! is_array($child)) {
                    $out[] = $child;
                    continue;
                }
                if (($child['id'] ?? '') === $id) {
                    if ($extracted === null) {
                        $extracted = $child;
                    }
                    continue;
                }
                $out[] = $this->extractById($child, $id, $extracted);
            }

            return $out;
        }

        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = $this->extractById($v, $id, $extracted);
            }
        }

        return $node;
    }

    /**
     * @param  array<mixed>  $node
     */
    private function treeHasId(array $node, string $id): bool
    {
        if (($node['id'] ?? null) === $id) {
            return true;
        }
        foreach ($node as $value) {
            if (is_array($value) && $this->treeHasId($value, $id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $arr
     */
    private function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
