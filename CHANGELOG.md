# Changelog

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

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
