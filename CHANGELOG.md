# Changelog

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

## [1.3.3] - 2026-09-10

### Fixed

- **Bottom merge = one stack** (`hero-carousel.js`): when page bottom + `global.bottom` both have items, place them **in sequence** (page-slot banners first, then global) as **one continuous vertical stack** on a single mount — not two separate sections, and **not** a carousel.
- **Single bottom/top host:** always clear/hide the non-primary mount after merge so only one host stays visible (fixes duplicate bottom sections).
- **Top unchanged:** page + `global.top` still merge into one carousel (same as 1.3.2).

### Changed

- Version **1.3.3**. Prefer `hero-carousel.js` only — no theme edits.

### Install

```
php82 artisan module:update custom-ad_slots --source=bundled --force --layout-strategy=overwrite
php82 artisan hooks:clear
php82 artisan cache:clear
```

## [1.3.2] - 2026-09-10

### Changed

- **Merge page + global top/bottom into one host** (`hero-carousel.js`): when the current path has a page slot (`home.top` / `shop.*.top` / `board.*.top` / `mypage.top`, matching bottoms) **and** `global.top` / `global.bottom` also have items, render **one carousel (top)** / **one stack (bottom)** instead of two separate mounts.
- **Dedupe** merged items by ad `id`; order is **page-slot items first** (API `sort_order`), then global items.
- **Host preference:** prefer `cas_page_top_mount` / `cas_page_bottom_mount` when both sides have items (or page-only); use `ad_global_top_hero` / `ad_global_bottom_stack` when only global has items. The unused mount is cleared/hidden so banners are not shown twice.
- **Single-side unchanged:** page-only or global-only keeps prior mount + carousel/stack rules (`home.top` / `global.top` carousel). Merged top always uses carousel.
- **Checkout / excluded pages:** page mounts still cleared; global mounts continue to show when they have items (unchanged).

### Unchanged

- No theme edits; `ad_global__user_base.json` untouched. Home ads (`home.top` / `home.bottom`) still work via the same page mounts.

### Install

```
php82 artisan module:update custom-ad_slots --source=bundled --force --layout-strategy=overwrite
php82 artisan hooks:clear
php82 artisan cache:clear
```

## [1.3.1] - 2026-09-10

### Fixed

- **Reverted 1.2.9 / 1.3.0;** restored **1.2.8** home-working behavior (path-routed mounts + home/global carousel). Main page top/bottom ads confirmed working on 1.2.8.

### Changed

- Tree restored from `5b3a1b7` (v1.2.8). Removed `ad_page_slots__user_base.json` (1.3.0 multi-page experiment). Version bumped to **1.3.1**.

### Install

```
php82 artisan module:update custom-ad_slots --source=bundled --force --layout-strategy=overwrite
php82 artisan hooks:clear
php82 artisan cache:clear
```

## [1.3.0] - 2026-09-10

### Added

- **`ad_page_slots__user_base.json`** (priority 81): path-conditioned empty Div mounts on official `_user_base` `main_content` for shop / board / mypage top+bottom slots (same channel as working home `cas_page_*`). Path `if` only (feat-style `_global.shopBase` / `location.pathname`); **no** API-length gate — JS hides empty mounts.
- Slot mounts use stable ids (`ad_shop_list_top_mount`, …) + `data-cas-ad-slot` so existing `hero-carousel.js` fills them.

### Changed

- **Event Hook:** disable v1.2.9 native content-tree stacks (`INJECT_NATIVE_PAGE_STACKS=false`) to avoid duplicate ads once `_user_base` mounts work. **`home.mid` still injected.**
- **JS:** map new `*_mount` ids; when a dedicated `[data-cas-ad-slot]` page mount exists, skip `cas_page` fill for that slot (home unchanged). `resolvePageSlots` shopBase hardening retained as backup.

### Unchanged

- **`ad_global__user_base.json`** — do not edit; `global.top` / `global.bottom` / `cas_page_*` home path mounts stay as in 1.2.8/1.2.9.
- Checkout / order-complete exclusion; no theme edits.

### Install

```
php82 artisan module:update custom-ad_slots --source=bundled --force --layout-strategy=overwrite
php82 artisan hooks:clear
php82 artisan cache:clear
```

## [1.2.9] - 2026-09-10

### Fixed

- **Non-home ads (shop / board / mypage):** Event Hook injects feat-style **native** banner stacks (`if` + `iteration` + inlined `_banner_list`) into page content. Path-routed `cas_page_*` JS alone was unreliable for those URLs.
- **Home / global untouched:** `home.top` / `home.bottom` still via `cas_page` path mounts; `global.top` / `global.bottom` and hero-carousel script on `_user_base` unchanged. `home.mid` insertion unchanged. Checkout / order layouts still excluded.
- **JS safety:** `findMounts` no longer claims wrap ids without `data-cas-ad-slot` (avoids wiping native stacks). If a native stack is present, `cas_page` role mounts are cleared to prevent double ads. Optional `shopBase` hardening in `resolvePageSlots` (home `/` detection unchanged).

### Added

- `AdPlacementFragments::nativeStackWrap()` — feat-style native stack fragment.

### Install

```
php82 artisan module:update custom-ad_slots --source=bundled --force --layout-strategy=overwrite
php82 artisan hooks:clear
php82 artisan cache:clear
```

## [1.2.8] - 2026-09-10

### Fixed

- **Reverted 1.2.7 native path stacks that broke home ads;** restored **1.2.6** behavior (path-routed mounts + home/global carousel).

### Changed

- Tree restored from `efad177` (v1.2.6). Version bumped to **1.2.8** (do not reuse 1.2.6).

### Install

```
php82 artisan module:update custom-ad_slots --source=bundled --force --layout-strategy=overwrite
php82 artisan hooks:clear
php82 artisan cache:clear
```

## [1.2.7] - 2026-09-10

### Fixed

- **Ads on all pages (feat-style native stacks):** `_user_base` overlay now embeds **path-conditioned** banner stacks (shop/board/mypage/home bottoms + tops) with `data_sources` + `iteration` + **inlined** `_banner_list.json` body. Layout engine renders images — no dependency on empty `data-cas-ad-slot` mounts or `cas_page_*` path JS (those only worked reliably on home).
- Module `partial: partials/ads/_banner_list.json` often does **not** resolve inside official theme; Event Hook / overlay **inline** the banner item children (same markup as feat theme).

### Changed

- Keep **JS hero carousel** only for `global.top` and `home.top` (official theme has no `AdHeroCarousel`).
- `global.bottom` and all page top/bottom slots: native stacked banners with path `if` using `_global.shopBase` + `location.pathname` (checkout / order-complete excluded).
- Remove `cas_page_top_mount` / `cas_page_bottom_mount` to prevent double ads.
- Event Hook **home.mid** uses native `iterWrap` (iteration + inlined banners).
- Page overlay files (`ad_shop_*`, `ad_board_popular`) retired to stubs; slot data_sources live on `_user_base`.

### Install

```
php82 artisan module:update custom-ad_slots --source=bundled --force --layout-strategy=overwrite
php82 artisan hooks:clear && php82 artisan cache:clear
```

## [1.2.6] - 2026-09-10

### Fixed

- **Page ads via `_user_base` path-routed mounts (fixes main-only):** always-on `#cas_page_top_mount` / `#cas_page_bottom_mount` (`data-cas-ad-role`) on `_user_base` so every page has DOM mounts. `hero-carousel.js` `resolvePageSlots()` maps `location.pathname` → slot keys (home/shop/board/mypage); checkout/order URLs hide page mounts. SPA nav (popstate + history patch + interval + MutationObserver) re-resolves and rebuilds when path/slot changes.
- Event Hook per-page `slots.content[0]` injection remains optional backup (`INJECT_PAGE_MOUNTS_BACKUP=false` by default to avoid duplicates); **home.mid** still inserted between home rows.

### Changed

- Primary page ad delivery no longer depends on Event Hook mount injection succeeding.

## [1.2.5] - 2026-09-10

### Fixed

- **Ads on all pages (not only home):** `AdPlacementLayoutListener` injects empty `data-cas-ad-slot` mounts into the live content tree (`main_content` **or** `slots.content[0]` children). Official shop/board/mypage layouts have no reliable `main_content` for overlay inject — overlays no longer own page mounts.
- **Always-on mounts:** removed brittle `if: length>0` from mount wraps and `_user_base` global top/bottom. JS hides empty mounts (`display:none`) and shows them when placements exist — never clears non-mount page content.
- **Checkout safety:** never inject ad mounts into `shop/checkout`, `checkout`, `order_complete`, `guest_order_show`, or any layout name containing `checkout`.

### Added

- Slot keys: `mypage.top` / `mypage.bottom`, `board.index.top/bottom`, `board.show.top/bottom`, `board.form.top/bottom`, `board.boards.top/bottom` (admin form + ko/en + resource labels). Q&A boards use normal `board/index`·`board/show`; `mypage/inquiries` maps to mypage slots.

### Changed

- Page overlay extensions (`ad_home`, `ad_shop_*`, `ad_board_popular`) are **data_sources only**; listener owns mount insertion by layout map. `_user_base` still loads `hero-carousel.js` and hosts `global.top` / `global.bottom`.

## [1.2.4] - 2026-09-10

### Fixed

- **All page top/bottom ad slots** (home / global / shop / board) render from **placements API** into empty `data-cas-ad-slot` mounts — layout iteration no longer paints banners (G7 often flattened or omitted images on non-home pages).
- `home.top` / `global.top`: Bunjang carousel (full slide list, arrows/dots/autoplay/swipe), matching feat `AdHeroCarousel` `items`.
- Other slots: stacked banners (desktop/mobile, link, `prevent_right_click`, `open_in_new_tab`) rendered **inside** the mount.
- Site-wide script load via `_user_base` extension only; `home.mid` Event Hook injects the same mount stub.

## [1.2.3] - 2026-09-10

### Fixed

- Carousel now builds a sibling **host DOM** outside React management (React was resetting inline styles on slide nodes → stacked banners). Source roots stay `display:none`; host owns aspect ratio, one-slide-visible, autoplay/controls/swipe.
- Hero roots get reliable `cas-hero` className (and `ad_global_top_hero` id on global) in addition to `data-cas-hero`.

## [1.2.2] - 2026-09-10

### Fixed

- `AssetController` asset path: `dirname(__DIR__, 3)` resolved to `src/` (controller under `Public/`), so `hero-carousel.js` 404'd. Use `dirname(__DIR__, 4)` (module root). Fixed route `assets/hero-carousel.js`; missing file returns `/* missing */` 200 instead of hard 404.

## [1.2.1] - 2026-09-10

### Added

- **home.top / global.top Bunjang-style hero carousel** via module-owned markup (`data-cas-hero`) + `resources/assets/hero-carousel.js` (autoplay 4s, pause on hover, chevrons/dots, swipe). Official `gnuboard/g7-template-sirsoft-basic` does not ship `AdHeroCarousel`, so this module does not use that composite name.
- Asset route `GET /api/modules/custom-ad_slots/assets/{file}` (whitelist: `hero-carousel.js`).
- Page **bottom** slots (stacked banners, same as mid/bottom style): `shop.list.bottom`, `shop.detail.bottom`, `shop.cart.bottom`, `board.popular.bottom` — layout extensions + admin slot options + ko/en labels.

### Changed

- `home.top` / `global.top` preserve `open_in_new_tab`, `prevent_right_click`, desktop/mobile/legacy image fields on carousel slides.
- Partials `_hero_carousel.json` / `_hero_carousel_global.json` updated for carousel root + slides (JS enhances).

## [1.2.0] - 2026-09-10

### Added

- **공식 테마(Event Hook / Layout Extensions) 광고 주입**: `gnuboard/g7-template-sirsoft-basic` 앵커에 테마 수정 없이 광고 슬롯을 붙입니다.
  - Overlay extensions: `global.top` / `global.bottom` (`_user_base`의 `main_content_area`·`footer`), `home.top`/`home.bottom`, `shop.list.top`, `shop.detail.top`, `shop.cart.top`, `board.popular.top` (`main_content`).
  - Event Hook `AdPlacementLayoutListener`: `home.mid`를 홈 1행·2행 사이에 삽입, `shop.detail.top`을 뒤로가기 버튼 뒤로 재배치.
  - 모듈 소유 partials: `resources/layouts/partials/ads/_banner_list.json`, `_hero_carousel.json`, `_hero_carousel_global.json` (공식 테마는 AdHeroCarousel 미포함 → 스택 배너로 렌더).
- 하드 제외: 메뉴/검색/아이콘/홈디자인 UI는 이식하지 않음 (광고만).

## [1.1.22] - 2026-09-08

### Changed

- 오른쪽 토글 버튼: **현재 상태의 반대** 글자·색 (활성→「비활성」+빨강, 비활성→「활성」+초록). 상태 뱃지는 현재 상태 유지.

## [1.1.21] - 2026-09-08

### Changed

- 오른쪽 토글 버튼은 **현재 상태의 반대**로 글자·색을 표시합니다(활성이면 「비활성」+빨강, 비활성이면 「활성」+초록). 상태 뱃지는 현재 상태 그대로입니다.

## [1.1.20] - 2026-09-08

### Changed

- 관리자 목록 오른쪽 토글 버튼 라벨을 **전환 대상**으로 표시합니다(현재 활성이면 「비활성」, 비활성이면 「활성」).

## [1.1.19] - 2026-09-08

### Fixed

- 관리자 목록 **상태 뱃지** 색을 원래대로 복구했습니다(활성=초록/검정, 비활성=빨강/흰글씨). 활성/비활성 색 반전은 오른쪽 「활성」 토글 버튼에만 유지합니다.

## [1.1.18] - 2026-09-08

### Added

- 광고별 `open_in_new_tab` 플래그(기본값 `true`, 기존 `_blank` 동작 유지). 관리자 폼 「새 창에서 열기」 체크박스와 placements API(`open_in_new_tab`)에 포함됩니다.

### Changed

- 관리자 목록 활성/비활성 뱃지·「활성」 토글 버튼 색상을 서로 바꿨습니다(활성=빨강/흰글씨, 비활성=초록/검정글씨).
- 관리자 목록 우측 액션 버튼 순서를 복제 → 활성 → 수정 → 삭제로 변경했습니다.
