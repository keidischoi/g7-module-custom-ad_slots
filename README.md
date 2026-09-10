# custom-ad_slots — G7 광고 슬롯 모듈

홈/쇼핑몰 영역별 **정적(static)** · **동적(dynamic)** 광고를 관리하고, 공개 placements API로 템플릿에 노출합니다.

| 항목 | 값 |
|------|-----|
| identifier | `custom-ad_slots` |
| Namespace | `Modules\Custom\AdSlots` |
| Composer | `modules/custom-ad_slots` |
| 버전 | `1.2.6` |

## 슬롯 키

| slot_key | 용도 |
|----------|------|
| `global.top` | 전체 · 상단 |
| `global.bottom` | 전체 · 하단 (푸터 직전) |
| `home.top` | 홈 상단 |
| `home.mid` | 홈 중단 |
| `home.bottom` | 홈 하단 |
| `shop.list.top` | 쇼핑몰 목록 상단 |
| `shop.list.bottom` | 쇼핑몰 목록 하단 |
| `shop.detail.top` | 상품 상세 상단 |
| `shop.detail.bottom` | 상품 상세 하단 |
| `shop.cart.top` | 장바구니 상단 |
| `shop.cart.bottom` | 장바구니 하단 |
| `board.popular.top` | 인기글 상단 |
| `board.popular.bottom` | 인기글 하단 |
| `board.index.top` / `board.index.bottom` | 게시판 목록 (Q&A 등) |
| `board.show.top` / `board.show.bottom` | 게시글 상세 |
| `board.form.top` / `board.form.bottom` | 게시글 작성 |
| `board.boards.top` / `board.boards.bottom` | 게시판 전체 목록 |
| `mypage.top` / `mypage.bottom` | 마이페이지 전 화면 |



## 공식 테마 광고 주입 (v1.2.6 · `_user_base` path-routed mounts)

테마 파일을 수정하지 않습니다. **페이지 광고는 `_user_base`에 고정된 마운트**(`cas_page_top_mount` / `cas_page_bottom_mount`)를 `hero-carousel.js`가 **URL 경로로 슬롯 키를 선택**해 채웁니다(Event Hook content 주입 실패와 무관하게 모든 페이지에서 DOM에 존재).

| 슬롯 | 방식 | 앵커 |
|------|------|------|
| `global.top` / `global.bottom` | overlay `ad_global__user_base.json` (always-on) | `_user_base` → `main_content_area` / `footer` + script |
| `home.top` / `home.bottom` / shop.* / board.* / mypage.* | `_user_base` path-routed mounts + JS | `#cas_page_top_mount` / `#cas_page_bottom_mount` (`data-cas-ad-role`) |
| `home.mid` | Event Hook | 홈 1행·2행 사이 |

**제외(페이지 마운트 숨김):** checkout / order_complete / guest_order URL·레이아웃 — 결제 화면 공백 방지.

확장 파일: `resources/extensions/ad_global__user_base.json`  
리스너: `src/Listeners/AdPlacementLayoutListener.php` (`home.mid` + optional backup mounts)  
스크립트: `resources/assets/hero-carousel.js` (`resolvePageSlots()`)

**렌더**: JS가 placements API로 캐러셀(`home.top`/`global.top`) 또는 스택 배너를 마운트 안에 그림. 빈 슬롯은 마운트만 `display:none`.

**제외 UI**: 메뉴/검색/아이콘/홈디자인 등 비광고 UI는 포함하지 않습니다.

## 공개 API

Prefix는 코어 `ModuleRouteServiceProvider`가 자동 적용합니다.

| Method | Path | 설명 |
|--------|------|------|
| `GET` | `/api/modules/custom-ad_slots/placements` | 활성+스케줄 내 전체, **슬롯별 그룹** |
| `GET` | `/api/modules/custom-ad_slots/placements?slot=home.top` | 특정 슬롯 목록 (`sort_order` 정렬) |

필터 조건: `is_active=true` 이고 `starts_at`/`ends_at` 윈도우 안(또는 null).


## 반응형 이미지 (v1.1.0)

번장(Bunjang) 스타일 히어로/캐러셀용 **데스크톱·모바일 분리 이미지**를 지원합니다.

| 컬럼 | 설명 |
|------|------|
| `image_url` | 레거시 단일 이미지 (폴백) |
| `image_url_desktop` | 데스크톱/히어로 (와이드 캐러셀 권장) |
| `image_url_mobile` | 모바일 이미지 |
| `bg_color` | 레터박스 배경색 (예: `#f5f5f5`, max 32) |

리소스 응답에 해석된 헬퍼가 포함됩니다.

| 필드 | 해석 |
|------|------|
| `image_desktop` | `image_url_desktop` ?: `image_url` |
| `image_mobile` | `image_url_mobile` ?: `image_url_desktop` ?: `image_url` |

기존 데이터는 `image_url`만 있어도 헬퍼가 그대로 폴백합니다.

### 업그레이드 (NAS)

모듈 코드를 최신으로 맞춘 뒤 마이그레이션을 실행하세요.

```bash
cd /volume1/web/3ds/modules/custom-ad_slots
git pull origin main

cd /volume1/web/3ds
php artisan migrate
# 또는 모듈 스코프:
# php artisan module:migrate custom-ad_slots
php artisan cache:clear
```

추가 마이그레이션: `2026_09_08_000002_add_responsive_images_to_ad_slots_items.php`

## 관리자 API

인증: `auth:sanctum` + `permission:admin,custom-ad_slots.ads.*` (hello_module과 동일 스타일).

| Method | Path | Permission |
|--------|------|------------|
| `GET` | `/api/modules/custom-ad_slots/admin/ads` | `ads.read` |
| `POST` | `/api/modules/custom-ad_slots/admin/ads` | `ads.create` |
| `GET` | `/api/modules/custom-ad_slots/admin/ads/{id}` | `ads.read` |
| `PUT` | `/api/modules/custom-ad_slots/admin/ads/{id}` | `ads.update` |
| `POST` | `/api/modules/custom-ad_slots/admin/ads/{id}/duplicate` | `ads.create` |
| `PATCH` | `/api/modules/custom-ad_slots/admin/ads/{id}/toggle` | `ads.update` |
| `DELETE` | `/api/modules/custom-ad_slots/admin/ads/{id}` | `ads.delete` |

관리자 메뉴: `/admin/ad-slots` (`resources/routes/admin.json` + `Module::getAdminMenus()`).

> 관리 레이아웃 JSON은 목록/폼 **최소** 구현입니다. 우선 curl로 CRUD를 검증한 뒤, 사이트 admin 템플릿에 맞게 폼 필드를 확장하세요.

## NAS 설치 (Synology 예시)

경로 예: `/volume1/web/3ds` = G7 루트.

```bash
# 1) 모듈 복사/클론
cd /volume1/web/3ds/modules
git clone https://github.com/keidischoi/g7-module-custom-ad_slots.git custom-ad_slots
# 또는 scp/rsync 로 custom-ad_slots 디렉터리 통째 복사

# 2) 소유권 (웹 서버 유저 — DSM 환경에 맞게 http 또는 httpd)
chown -R http:http /volume1/web/3ds/modules/custom-ad_slots

# 3) 오토로드 / 확장 반영 (G7 루트에서)
cd /volume1/web/3ds
php artisan extension:update-autoload
# 또는 모듈 composer dump가 필요하면:
# php artisan module:composer-install custom-ad_slots
# composer dump-autoload

# 4) 설치 + 마이그레이션 + 활성화
php artisan module:install custom-ad_slots
php artisan migrate
# 마이그레이션이 모듈 설치에 포함되지 않는 환경이면:
# php artisan module:migrate custom-ad_slots   # 커맨드명이 다를 수 있음 — 코어 문서 확인
php artisan module:activate custom-ad_slots

# 5) 캐시 정리
php artisan cache:clear
php artisan route:clear
```

권한/메뉴는 설치·업데이트 시 `Module.php`의 `getPermissions()` / `getAdminMenus()`로 동기화됩니다.

## 템플릿 슬롯 연결

1. 유저 레이아웃(홈/상품목록 등)에 partial include 또는 동일 data_source 패턴을 넣습니다.
2. 참고 partial: `resources/layouts/partials/_ad_slot.json`
   - `data_sources.placements` → `GET /api/modules/custom-ad_slots/placements?slot={{slot_key}}`
   - `type=static`: `image_url` + `link_url` 앵커/이미지
   - `type=dynamic`: `html_content` / `script_src` — **아래 XSS 경고 필독**

예시(개념):

```text
홈 레이아웃 content 슬롯에
  partial _ad_slot  (props.slot_key = home.top)
를 배치하고, mid/bottom도 동일하게 복제.
```

## ⚠️ XSS 경고 (dynamic)

`html_content`, `script_src`는 **신뢰된 관리자 입력만** 저장하세요.

- 공개 API가 그대로 JSON으로 내려줍니다.
- 레이아웃에서 raw HTML/스크립트를 렌더하면 XSS가 됩니다.
- sanitize 없이 `dangerouslySetInnerHTML` / raw 바인딩을 쓰지 마세요.
- 외부 광고 네트워크 스크립트는 CSP·도메인 화이트리스트를 검토하세요.

## curl 샘플 (정적 광고 생성)

관리자 Sanctum 토큰을 `TOKEN`에 넣습니다.

```bash
# 생성
curl -sS -X POST 'https://YOUR_HOST/api/modules/custom-ad_slots/admin/ads' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "slot_key": "home.top",
    "type": "static",
    "title": "홈 상단 배너",
    "image_url": "https://via.placeholder.com/1200x200.png?text=Home+Top",
    "image_url_desktop": "https://via.placeholder.com/1920x480.png?text=Desktop",
    "image_url_mobile": "https://via.placeholder.com/768x960.png?text=Mobile",
    "bg_color": "#f5f5f5",
    "link_url": "https://example.com",
    "sort_order": 0,
    "is_active": true
  }'

# 공개 조회
curl -sS 'https://YOUR_HOST/api/modules/custom-ad_slots/placements?slot=home.top' \
  -H 'Accept: application/json'

# 전체 그룹 조회
curl -sS 'https://YOUR_HOST/api/modules/custom-ad_slots/placements' \
  -H 'Accept: application/json'

# 토글
curl -sS -X PATCH "https://YOUR_HOST/api/modules/custom-ad_slots/admin/ads/1/toggle" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

샘플 시더: `database/seeders/Sample/AdSlotSampleSeeder.php`  
(`php artisan module:seed custom-ad_slots --sample` — 코어 시더 디스커버리 지원 시)

## 디렉터리 요약

```text
module.json / module.php / composer.json / LICENSE
database/migrations/..._create_ad_slots_items_table.php
database/migrations/..._add_responsive_images_to_ad_slots_items.php
database/seeders/Sample/AdSlotSampleSeeder.php
src/Models/AdSlotItem.php
src/Services/AdSlotService.php
src/Http/Controllers/Public/PlacementController.php
src/Http/Controllers/Admin/AdSlotItemController.php
src/Http/Requests/Admin/{Store,Update}AdSlotItemRequest.php
src/Http/Resources/AdSlotItemResource.php
src/routes/api.php
resources/routes/admin.json
resources/layouts/admin/admin_ad_slot_{list,form}.json
resources/layouts/partials/_ad_slot.json
resources/layouts/partials/ads/{_banner_list,_hero_carousel,_hero_carousel_global}.json
resources/extensions/ad_*.json
src/Listeners/AdPlacementLayoutListener.php
src/Support/AdPlacementFragments.php
resources/lang/{ko,en}.json
src/lang/{ko,en}/messages.php
```

## 오픈 이슈 / 확인 필요

- NAS에서 `module:migrate` / 마이그레이션 자동 실행 여부는 G7 버전에 따라 다름 → `migrate` 후 `ad_slots_items` 테이블 존재 확인.
- `optional.sanctum`, `permission:admin,...` 미들웨어 alias는 코어 G7 기준(hello_module에서 확인). 커스텀 코어면 alias 이름 조정.
- Admin 폼 레이아웃은 최소 UI — 필드 입력 폼은 사이트 admin 컴포넌트 세트에 맞게 보강 권장.
- 테이블명 `ad_slots_items`는 요청 스펙 그대로(모듈 prefix 없음). 다중 커스텀 모듈과 충돌 시 rename 검토.
