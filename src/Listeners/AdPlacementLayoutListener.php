<?php

namespace Modules\Custom\AdSlots\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Custom\AdSlots\Support\AdPlacementFragments;

/**
 * Official sirsoft-basic lacks stable ids inside home/shop/board slot trees.
 * Layout extensions cover global + page *.top/bottom via main_content anchors.
 * This Event Hook inserts home.mid between official home row1 and row2 (feat parity),
 * ensures home.mid data_source exists, and repositions shop.detail.top after the
 * back-button when the overlay left it at the very top.
 *
 * Ads only — no menu/search/icon/home-design UI.
 */
class AdPlacementLayoutListener implements HookListenerInterface
{
    private const HOME = 'home';

    private const SHOP_SHOW = 'shop/show';

    private const MID_WRAP_ID = 'ad_home_mid_wrap';

    private const DETAIL_WRAP_ID = 'ad_shop_detail_top_wrap';

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
        $name = (string) ($layout['layout_name'] ?? $layout['name'] ?? '');

        if ($name === self::HOME) {
            $layout = $this->ensureDataSource($layout, 'ad_home_mid', 'home.mid', 'Ad home.mid');
            $layout = $this->insertHomeMid($layout);
        }

        if ($name === self::SHOP_SHOW) {
            $layout = $this->ensureDataSource($layout, 'ad_shop_detail_top', 'shop.detail.top', 'Ad shop.detail.top');
            $layout = $this->repositionShopDetailTop($layout);
        }

        return $layout;
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

        $wrap = AdPlacementFragments::iterWrap(
            self::MID_WRAP_ID,
            '=== Ad slot: home.mid (1행과 2행 사이) ===',
            'ad_home_mid',
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
     * Official home: slots.content[0] = Container; children[0]=row1, [1]=row2, [2]=bottom.
     * Insert mid between row1 and row2. Also works if extension already prepended home.top
     * as a sibling under main_content (merged tree) — then look for row comments.
     *
     * @param  array<mixed>  $node
     * @param  array<string, mixed>  $wrap
     * @return array<mixed>
     */
    private function insertMidIntoHomeSlots(array $node, array $wrap, bool &$done): array
    {
        if ($done) {
            return $node;
        }

        // Prefer explicit home Container children list
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

        // Official / feat home rows
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
            // Skip already-injected top ad wrap
            if ($id === 'ad_home_top_wrap' || str_contains($comment, 'home.top')) {
                continue;
            }
            // First real content row
            if (str_contains($comment, '1행') || str_contains($comment, 'Welcome') || $comment !== '') {
                return $i + 1;
            }
        }

        // Fallback: after first child
        return min(1, count($children));
    }

    /**
     * Move shop.detail.top wrap to immediately after the first child (back button),
     * matching feat theme placement.
     */
    private function repositionShopDetailTop(array $layout): array
    {
        $section = null;
        if (isset($layout['components']) && is_array($layout['components'])) {
            $layout['components'] = $this->extractById($layout['components'], self::DETAIL_WRAP_ID, $section);
        }
        if ($section === null && isset($layout['slots']) && is_array($layout['slots'])) {
            $layout['slots'] = $this->extractById($layout['slots'], self::DETAIL_WRAP_ID, $section);
        }
        if ($section === null) {
            $section = AdPlacementFragments::iterWrap(
                self::DETAIL_WRAP_ID,
                'Ad slot: shop.detail.top (헤더/뒤로가기 다음)',
                'ad_shop_detail_top',
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
            // Prefer the shop/show outer container (has back button as first Button)
            $first = $children[0] ?? null;
            if (is_array($first) && (($first['name'] ?? '') === 'Button' || str_contains((string) ($first['comment'] ?? ''), '뒤로'))) {
                // Remove if already present at index 0 (prepended by overlay)
                if (is_array($children[0] ?? null) && ($children[0]['id'] ?? '') === self::DETAIL_WRAP_ID) {
                    array_shift($children);
                    $first = $children[0] ?? null;
                }
                $insertAt = 1;
                // If first is still the ad wrap somehow, place after next
                if (is_array($first) && ($first['id'] ?? '') === self::DETAIL_WRAP_ID) {
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
        $isList = array_keys($node) === range(0, count($node) - 1);
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
}
