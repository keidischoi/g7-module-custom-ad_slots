# Changelog

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

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
