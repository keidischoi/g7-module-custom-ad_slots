<?php

/**
 * 1.4.31 빈 광고 자리가 새로고침 때 여백을 차지했다 사라지며 아래 내용(머리말 · 본문)이 밀리던 것
 *
 *   php tests/empty_gap.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$ok = 0;
$bad = 0;
function check(string $name, bool $cond): void
{
    global $ok, $bad;
    $cond ? $ok++ : $bad++;
    echo ($cond ? '✓ ' : '✗ ').$name."\n";
}

$hidden = [];
$walk = function ($n) use (&$walk, &$hidden): void {
    if (! is_array($n)) {
        return;
    }
    $p = $n['props'] ?? null;
    if (is_array($p) && (isset($p['data-cas-ad-slot']) || isset($p['data-cas-ad-role']) || in_array($n['id'] ?? '', ['ad_global_top_wrap', 'ad_global_bottom_wrap'], true))) {
        $hidden[$n['id'] ?? '?'] = (($p['style']['display'] ?? '') === 'none');
    }
    foreach ($n as $v) {
        if (is_array($v)) {
            $walk($v);
        }
    }
};
$walk(json_decode((string) file_get_contents($root.'/resources/extensions/ad_global__user_base.json'), true));
foreach (['ad_global_top_wrap', 'ad_global_top_hero', 'cas_page_top_mount', 'ad_global_bottom_wrap', 'cas_page_bottom_mount', 'ad_global_bottom_stack'] as $id) {
    check("처음엔 숨김: $id", $hidden[$id] ?? false);
}

$frag = (string) file_get_contents($root.'/src/Support/AdPlacementFragments.php');
check('리스너가 넣는 광고 자리도 처음엔 숨김', substr_count($frag, "'style' => ['display' => 'none']") === 2);

$js = (string) file_get_contents($root.'/resources/assets/hero-carousel.js');
check('광고를 그릴 때 감싸개도 보임', str_contains($js, 'syncWrap(mount, true);'));
check('다 비면 감싸개 다시 숨김', str_contains($js, 'syncWrap(mount, false);'));

$ver = json_decode((string) file_get_contents($root.'/module.json'), true)['version'] ?? '';
check('화면 스크립트 ?v = 모듈 버전', str_contains((string) file_get_contents($root.'/resources/extensions/ad_global__user_base.json'), 'hero-carousel.js?v='.$ver) && str_contains($js, 'CAS_AD_VERSION = "'.$ver.'"'));

echo "\n$ok 통과 · $bad 실패\n";
exit($bad ? 1 : 0);
