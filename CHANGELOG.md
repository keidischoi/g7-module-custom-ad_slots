## [1.4.22] - 2026-09-23

### Fixed
- **수정 화면 image_url* Input 미표시:** G7 레이아웃 표현식에서 `??` / `?.`(optional chaining)이 URL Input `value`·`onSuccess` hydrate·Save body에서 실패해 빈 값으로 고정되거나 저장 payload가 비는 문제.
  - URL Input: `value`를 `{{(_local.form && _local.form.image_url*) || ''}}`로 단순화, `key`는 `form.id`(로딩 후 1회 remount)만 사용 — has/empty remount 금지.
  - `ad` onSuccess / FileUploader `initialFiles` / `onUploadComplete` / Save body에서 `??`·`?.` 제거 (`||` + 단락 평가).
  - `ad-slot-image-upload.js`: GET `/admin/ads/:id` 성공 시 image_url* Input·`_local.form` silent hydrate (change 미발화 → 빈값 DELETE/forget 방지).
- **모바일 업로드 후 DB 미반영:** 동일 표현식 실패로 `form.image_url_mobile`/Save body가 비고, 빈 Input change가 `DELETE .../noop?field=image_url_mobile` → `forgetUrl`까지 호출하며 remember 백업도 지워짐.
  - 필드별 collection·emit·uploadParams·remember 경로는 유지; assist가 query/FormData/`uploadParams.field`에서 `image_url_mobile`을 확실히 추출해 해당 필드만 setState.

### Unchanged
- FileUploader 박스 UI, `autoUpload:false`, `upload_token` 스테이징, maxFiles:1, collections `ad_slot_image_url` / `_desktop` / `_mobile`.
- Save `disabled`는 `_local.saving`만. bare path emit 게이트 유지. remembered GET 루프 없음. `admin-page-content-responsive` only.

### Meta
- Version **1.4.22**. 관리자 업로드 JS `ad-slot-image-upload.js?v=1.4.22`. 광고 JS `hero-carousel.js` CAS_AD_VERSION 1.4.22.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.21] - 2026-09-23

### Fixed
- **3개 이미지 슬롯 상호 삭제 (공유 collection):** FileUploader 세 박스가 모두 `collection: "ad_slot_images"` + `maxFiles:1`을 공유해, 다음 필드 업로드 시 같은 collection 스토어가 형제 칩을 교체/삭제함 → 마지막만 남음.
  - 필드별 독립 collection: `ad_slot_image_url` / `ad_slot_image_url_desktop` / `ad_slot_image_url_mobile` (maker_bids `images` vs `archives` 분리와 동일 패턴).
  - 각 박스 `maxFiles: 1` 유지. 한 박스에서 교체는 그 필드만; 다른 박스 칩/URL은 유지.
- **업로드 직후 URL 미반영:** 선택 시 해당 필드만 `emitEvent upload:ad_image_url*` → 즉시 POST `/uploads`. `onUploadComplete` + assist JS(XHR/fetch)가 **해당 필드만** `image_url*` input/`_local.form`에 즉시 채움 (Save 대기 없음). 형제 필드 setState 금지.
- **수정 화면 URL/칩 미표시:** `ad` dataSource `onSuccess`에서 `image_url*` + `uploader_image_url*`를 form에 명시 hydrate. `initialFiles`는 필드별 uploader 배열 유지.

### Unchanged
- FileUploader UI, `upload_token` 스테이징(backup merge), `autoUpload:false`, Save 시 emit 후 apiCall, has/empty remount 없음, remembered GET 루프 없음, path emit gate 없음, Save `disabled`는 `_local.saving`만, `admin-page-content-responsive`.
- 서버 remember 키는 `(user, token, field)` — 단일 필드 업로드 시 `forgetAllUrls` 호출 없음; clear는 해당 필드만.

### Meta
- Version **1.4.21**. 관리자 업로드 JS `ad-slot-image-upload.js?v=1.4.21`. 광고 JS `hero-carousel.js` CAS_AD_VERSION 1.4.21.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.20] - 2026-09-23

### Fixed
- **업로더 박스 UI 복원 (FileUploader):** 1.4.19 네이티브 `<input type=file>` 마운트를 제거하고, maker_bids `jobs_form` 패턴의 FileUploader 점선 박스(칩/1/1)를 레거시·데스크톱·모바일 세 필드에 복구.
- **upload_token 저장 병합 (maker_bids claim 경로):**
  - `GET /admin/form-defaults` → `upload_token` 발급 → 폼/`uploadParams`로 전달.
  - 업로드 시 서버가 `(user, token, field)`로 URL 스테이징.
  - 저장 시 FileUploader `uploadTriggerEvent` emit 후 apiCall; body의 `upload_token`으로 `mergeRememberedUrls`가 빈 `image_url*`를 채움.
  - **onUploadComplete → form.image_url setState에 의존하지 않음** (취약 경로 제거).
- **onFilesChange 단순화:** 카운트만(+빈 배열일 때만 URL/forget 클리어). remount/재emit/path 게이트/remembered GET 루프 없음.
- Save `disabled`는 `_local.saving`만. `uploading_*` 플래그 제거.
- Assist JS는 FileUploader 성공 XHR/fetch 관찰 + token 보정만 (네이티브 file input 주입 없음).

### Unchanged
- 크롬 `admin-page-content-responsive` only. `files`/`value` two-way 바인딩 없음.
- FileUploader `key`는 form id 안정 identity만 (has/empty remount 금지).
- clear/remove → URL 비움 + forget(+가능 시 파일 삭제) → 저장 시 DB null.

### Meta
- Version **1.4.20**. 관리자 업로드 JS `ad-slot-image-upload.js?v=1.4.20`. 광고 JS `hero-carousel.js?v=1.4.20`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.19] - 2026-09-23

### Fixed
- **이미지 DB 미반영 + 업로드 박스 미표시 (1.4.12–1.4.18 FileUploader 루프 종료):** G7 admin FileUploader의 emit/gate/`onUploadComplete`/remount 조합이 live에서 URL을 `_local.form`에 안정적으로 못 넣어 저장 body·DB가 비고, 칩도 안 보임. digital_product는 서버 `temp_key`로 첨부하므로 동일 증상을 피함.
  - **Option A (native file + preview + JS):** FileUploader 복합 컴포넌트 제거. URL 텍스트 입력 유지 + `data-cas-ad-upload-field` 마운트에 네이티브 `<input type=file>` 주입 + `<Img>` 미리보기 + 지우기 버튼.
  - `resources/assets/ad-slot-image-upload.js`: 파일 선택 → `POST /admin/uploads?field=…` (credentials/CSRF/Bearer) → 응답 `download_url`/`url`로 URL input 값·`setState(form.image_url*)`·미리보기 동기화. 저장은 일반 JSON body의 `image_url*`만 전송(업로드 emit 레이스 없음).
  - 지우기: URL/미리보기 비움 + `DELETE /admin/uploads/{id|noop}?field=…` (저장 시 DB null).
  - 수정 로드: `initLocal form`의 `image_url*`로 input·미리보기 표시.
- Remember 백업: `mergeRememberedUrls` 유지하되 **Session 우선 + Cache 폴백**(Synology Cache 깨짐 대비).

### Unchanged
- 크롬 `admin-page-content-responsive` only. `autoUpload:true` / shell hacks / `files`·`value` two-way 바인딩 없음.
- create/edit 하드 리프레시 좌측 메뉴(`_admin_base`) 유지.
- 광고 store/update는 JSON URL body (multipart 상품폼 아님).

### Meta
- Version **1.4.19**. 관리자 업로드 JS `ad-slot-image-upload.js?v=1.4.19`. 광고 JS `hero-carousel.js?v=1.4.19`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.18] - 2026-09-23

### Fixed
- **DB image_url* 미반영 (업로드+저장 후에도 null):** `onFilesChange` emit 게이트가 로컬 FileUploader 파일의 사전 `path`를 "이미 업로드됨"으로 오인해 `emitEvent upload`가 스킵 → POST `/uploads` 미발생 → `rememberUrl`/`onUploadComplete` 미실행 → 저장 body URL 공백 → DB null. 칩만 로컬 선택으로 보여 사용자는 업로드 성공으로 착각.
  - emit/setState 게이트: 서버 URL(`download_url` 또는 `url`이 `http`로 시작) 또는 `uploaded` 플래그가 있을 때만 재업로드 스킵. **bare `path`는 더 이상 스킵 조건이 아님.**
  - `apiEndpoints.upload`에 `?field=image_url*` 쿼리 추가 + 컨트롤러 `resolveUploadField()`가 query/FormData/`uploadParams.field` 모두 수용 → `rememberUrl` 확실히 기록.
  - `onUploadComplete` → form.image_url* / Input 바인딩, 저장 body의 uploader[0] URL 폴백 + `mergeRememberedUrls`는 유지(클라이언트 URL 우선).

### Unchanged
- `autoUpload: false` + `uploadTriggerEvent` (cold-load autoUpload 없음).
- remembered GET 재동기화 없음 (1.4.16).
- Save `disabled`는 `_local.saving`만 (1.4.17).
- `files`/`value` two-way 바인딩 및 remount-key/`has`/`empty` 키 핵 없음.
- 광고 store/update multipart 재도입 없음(JSON URL body + remember 병합으로 충분).

### Meta
- Version **1.4.18**. 광고 JS `hero-carousel.js?v=1.4.18`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```


## [1.4.17] - 2026-09-23

### Fixed
- **파일 선택 후 저장 버튼 영구 비활성화:** `onFilesChange`에서 `setState(uploading_*=true)`가 `emitEvent upload`보다 먼저 적용되면, emit의 `if (!_local.uploading_*)`가 이미 false가 되어 업로드가 스킵되고 플래그만 true로 남음 → `onUploadComplete`가 안 돌아 Save가 계속 disabled.
  - 레거시/데스크톱/모바일 세 FileUploader 모두 **emitEvent 먼저 → 그다음 uploading_*=true**. 안티루프 게이트(이미 업로드 중이거나 `download_url`/`path` 있으면 재emit 안 함)는 유지.
  - `onUploadComplete` / `onUploadError` / 빈 배열·삭제 경로의 `uploading_*=false` 클리어는 그대로.
  - Save `disabled`를 `_local.saving`만으로 축소(업로드 플래그에 묶지 않음). 업로드 중 저장은 `mergeRememberedUrls`가 in-flight URL을 반영. 클릭 시 upload-in-progress 토스트/게이트 제거.

### Unchanged
- `autoUpload: false` + `uploadTriggerEvent` (cold-load autoUpload 없음).
- 크롬 `admin-page-content-responsive` only.
- `files`/`value` two-way 바인딩 및 1.4.1–1.4.7 shell hacks 없음.
- FileUploader `key`는 form id 안정 identity만 사용 (1.4.14).
- remembered GET 재동기화 없음 (1.4.16). create·edit 공통 레이아웃.

### Meta
- Version **1.4.17**. 광고 JS `hero-carousel.js?v=1.4.17`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```


# Changelog

## [1.4.16] - 2026-09-23

### Fixed
- **"Too Many Attempts" 업로드 루프:** `onUploadComplete` → `GET .../uploads/remembered` → `setState(uploader_*)`가 `onFilesChange`를 다시 일으켜 `emitEvent upload`가 반복되고, `throttle:60,1`에 걸려 Laravel 429가 뜨며 업로더·폼 입력이 먹통이 되던 문제.
  - `onUploadComplete`에서 remembered 재동기화 API 호출 제거. 업로드 응답 `$args`에서 URL/`uploader_*`를 한 번만 추출해 `setState` (+ 성공 토스트).
  - `onFilesChange`의 `emitEvent`/`uploading_*=true`를 게이트: 이미 업로드 중이거나, 파일이 이미 서버 첨부(`download_url`/`path`)면 재emit 안 함. 선택당 최대 1회 업로드.
  - URL Input `value` 바인딩·`onChange` setState 유지. Input은 업로드/remembered 폴링을 트리거하지 않음(비울 때만 forget용 `DELETE noop?field=` 1회).
  - 제거/빈 배열 경로는 기존처럼 URL 클리어 + forget 1회 유지(재시도 스톰 없음).
- 안전망: 업로드 관련 라우트 throttle `60,1` → `180,1` (루프 제거가 본수정).
- `mergeRememberedUrls` / POST 업로드 시 `rememberUrl` 1회 기록은 유지 → 저장 시 DB URL 반영.

### Unchanged
- `autoUpload: false` + `uploadTriggerEvent` (cold-load autoUpload 없음).
- 크롬 `admin-page-content-responsive` only.
- `files`/`value` two-way 바인딩 및 1.4.1–1.4.7 shell hacks 없음.
- FileUploader `key`는 form id 안정 identity만 사용 (1.4.14).
- create·edit 공통 레이아웃; 레거시/데스크톱/모바일 동일.

### Meta
- Version **1.4.16**. 광고 JS `hero-carousel.js?v=1.4.16`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```


## [1.4.15] - 2026-09-23

### Fixed
- **업로드 후 URL 입력칸 비어 있음:** `image_url*` Input에 `value`/`onChange` 바인딩이 없어 `onUploadComplete` setState가 화면에 반영되지 않음. `value: {{_local.form.image_url*}}` + change→setState 추가.
- **`$args?.` 추출 실패:** 복잡한 optional-chaining 표현을 명시 가드로 단순화. 업로드 직후 `GET .../uploads/remembered?field=`로 서버 remember URL을 form에 재동기화(칩↔URL 일치).
- **삭제 후 저장 시 DB URL 부활:** 칩 제거 시 `DELETE .../uploads/noop?field=`로 remember forget + cleared 마커. `mergeRememberedUrls`는 cleared면 빈 요청을 다시 채우지 않음 → 저장 시 해당 컬럼 null.
- 레거시/데스크톱/모바일 세 필드 모두 동일 동작. create·edit 공통 레이아웃.

### Unchanged
- `autoUpload: false` + `uploadTriggerEvent` (cold-load autoUpload 없음).
- 크롬 `admin-page-content-responsive` only.
- `files`/`value` two-way 바인딩 및 1.4.1–1.4.7 shell hacks 없음.
- FileUploader `key`는 form id 안정 identity만 사용 (1.4.14).

### Meta
- Version **1.4.15**. 광고 JS `hero-carousel.js?v=1.4.15`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.14] - 2026-09-23

### Fixed
- **업로드 성공 토스트만 뜨고 칩/URL 사라짐:** FileUploader `key`가 `image_url ? has : empty`에 묶여 있어, 선택 시 `form.image_url=""` → `has→empty` remount로 pending 파일 소실, `onUploadComplete` 후 `empty→has` remount로 칩이 다시 날아감. URL 필드도 같이 비는 체감.
  - `key`를 안정 identity만 사용: `fu_image_url-{{form.id || (route.id ? loading : new)}}` (desktop/mobile 동일). URL 유무로 remount 안 함.
  - `onFilesChange`(파일 선택 시)에서 `form.image_url*`를 비우지 않음 — `uploading_*`만 true + `uploadTriggerEvent` emit. URL/uploader 배열 클리어는 빈 배열(제거) 및 onDelete/onRemove/onFileRemove에서만.
  - `onUploadComplete`는 기존처럼 `form.image_url*` + `form.uploader_image_url*` 기록 + 성공 토스트. remount가 없으므로 칩 유지 + URL 필드 채움.
- create·edit 공통 레이아웃(`admin_ad_slot_form`) — 기존 DB 이미지는 `initialFiles`(uploader_* `.length` 체크)로 그대로 hydrate.

### Unchanged
- `autoUpload: false` + `uploadTriggerEvent` (cold-load autoUpload 없음).
- 크롬 `admin-page-content-responsive` only.
- `files`/`value` two-way 바인딩 및 1.4.1–1.4.7 shell hacks 없음.
- store/update rememberUrl merge 안전망 유지.

### Meta
- Version **1.4.14**. 광고 JS `hero-carousel.js?v=1.4.14`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```


## [1.4.13] - 2026-09-23

### Fixed
- **이미지 URL 미저장:** `autoUpload:false` + 저장 시 `emitEvent` 직후 `apiCall` 레이스로 `_local.form.image_url*`가 비어 DB null이 되던 문제.
  - 업로드 API가 field별 URL을 서버 캐시에 기억(digital_product `temp_key` 패턴) → store/update 시 요청 URL이 비면 병합.
  - 업로드 응답을 digital_product와 동일한 `data.data` Attachment raw JSON으로 반환.
  - 선택 시 즉시 업로드 + `onUploadComplete` URL 추출 강화; 저장은 업로드 중이면 경고 후 중단(emit 레이스 제거).
  - 저장 body는 `form.image_url*` 또는 `uploader_*[0].download_url/url` 폴백.
- **수정 화면 업로드 박스 빈 칩:** `initialFiles`를 DP식으로 단순화(`.length`로 빈 배열 스킵) + `ad.data`/`ad.data.data` 폴백; `form.id`·URL 도착 시 key remount.
- **삭제:** delete URL에 `?field=`를 붙여 서버 remember 캐시 제거; 클라이언트 URL/uploader 배열 클리어 유지.

### Unchanged
- `autoUpload: false` (cold-load autoUpload 없음 — 1.4.8 사이드바 회귀 방지).
- 크롬 `admin-page-content-responsive` only.
- `files`/`value` two-way 바인딩 및 1.4.1–1.4.7 shell hacks 없음.

### Meta
- Version **1.4.13**. 광고 JS `hero-carousel.js?v=1.4.13`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.12] - 2026-09-23

### Fixed
- **등록(create)·수정(edit) 공통:** 동일 `admin_ad_slot_form` — 파일 선택→업로드→`image_url*` 기록→저장, 업로더 제거 시 실제 파일 삭제. create init에 빈 `uploader_image_url*` 포함, `onUploadComplete`가 URL과 uploader 배열을 함께 동기화.
- **이미지 업로드 저장 안 됨:** FileUploader가 Attachment를 `response.data.data`에서 읽는데, 업로드 API가 flat `data`만 반환해 `onUploadComplete`가 URL을 못 채우던 문제. `data.data`(digital_product 계약) + `thumbnail_url`로 맞춤. 선택 시 `uploadTriggerEvent` emit → URL 기록, 저장 시에도 동일 trigger 후 apiCall.
- **수정 시 업로드 박스에 기존 이미지 미표시:** `initialFiles: []` 하드코딩 제거. show 리소스에 one-way `uploader_image_url*` 배열 추가 → `initialFiles`로만 hydrate (`files`/`value` 바인딩 없음). `form.id` 도착 시 key remount.
- **업로더 삭제가 soft no-op:** `AdSlotUploadService::deleteByUploadId`로 managed path(`custom-ad_slots/Y/m/d/...`) 실제 삭제. 파일 id는 base64url(path). 외부 URL/noop은 soft-success. 클라이언트 `onDelete`/`onRemove`/`onFileRemove`/빈 `onFilesChange`에서 `image_url*` + `uploader_image_url*` 클리어.

### Unchanged (must not regress)
- FileUploader `autoUpload: false` + `uploadTriggerEvent` (cold-load autoUpload 없음).
- 크롬 `admin-page-content-responsive` only (폭 유틸 없음).
- 1.4.1–1.4.7 shell hacks / `files`·`value`·`image_url_*_files` two-way 바인딩 재도입 없음.

### Meta
- Version **1.4.12**. 광고 JS `hero-carousel.js?v=1.4.12`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.11] - 2026-09-23

### Changed
- **슬롯 기본 크기 라벨(동적):** size_mode가 `ratio`이면 「슬롯 기본 크기(비율) 일괄적용」, `fixed`이면 「슬롯 기본 크기(크기) 일괄적용」. 배치 수정 폼 H1·저장 토스트가 `_local.form.size_mode`에 연동. 목록 행에도 동일 구분. 목록/광고목록 상단 버튼은 중립 「슬롯 기본 크기 일괄적용」.
- **광고 목록 썸네일:** 고정 `h-16`+`object-cover` 제거. `thumb_aspect`(슬롯/아이템 비율 또는 fixed w/h)로 `aspect-ratio` 적용, `object-contain`으로 찌그러짐 방지.
- **활성/비활성 버튼:** 녹/빨 채움 제거 → 회색 아웃라인 컴팩트 버튼. 토글 동작·라벨 유지.

### Unchanged
- FileUploader `autoUpload: false` / 크롬 `admin-page-content-responsive` / 내부 카드 `max-w-5xl md:w-[70%]` (1.4.9–1.4.10 셸 유지).

### Meta
- Version **1.4.11**. 광고 JS `hero-carousel.js?v=1.4.11`.

### Deploy
```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.10] - 2026-09-23

### Added / Restored
- **광고 폼 크기 모드 UI** (v1.4.0): `size_mode`(inherit/ratio/fixed) + ratio 시 `aspect_desktop`/`aspect_mobile`, fixed 시 `width_px`/`height_px`/`max_width_px`. 생성 init 기본값·저장 body에 size 필드 포함.
- **광고 폼 슬롯 Select 전체 복원** (29개): board/shop/mypage 계열 + `maker_bids.top/bottom`, `share.top/bottom`, `page.top/bottom`.

### Changed
- **생성/수정 가로폭**: 크롬은 `admin-page-content-responsive` 유지(사이드바 회귀 방지). 내부 폼 카드·푸터에만 `max-w-5xl md:w-[70%] mx-auto`.
- **목록 활성/비활성 버튼 최소화**: `활성화하기`/`비활성하기` 라벨 유지, `px-2 py-1 text-xs`로 축소. 「광고 추가」버튼은 기존 크기 유지.
- **배치(placement) 폼**: 크롬 `admin-page-content-responsive-fluid` → `admin-page-content-responsive`, 카드에 `md:w-[70%]` 정렬.

### Unchanged (must not regress)
- FileUploader: `autoUpload: false`, `initialFiles`, `uploadTriggerEvent`, `*_files`/`value`/`files` 바인딩 없음 (digital_product 스타일).
- 1.4.1–1.4.7 edit-shell 실험(레이아웃 분리·progressive·빈 DS 등) 재도입 없음.

### Meta
- Version **1.4.10**. 광고 JS `hero-carousel.js?v=1.4.10`.

## [1.4.8] - 2026-09-23

### Changed

- **Restore pre-1.4.0 admin ad create/edit form** from **v1.1.22** baseline: single shared layout `admin_ad_slot_form` (`extends _admin_base`, show DS `endpoint .../{{route.id}}` + `if: {{route?.id}}` + `initLocal: form`, chrome `admin-page-content w-full max-w-none`).
- Edit route again points to `admin_ad_slot_form` (removed `admin_ad_slot_form_edit` layout/route split from 1.4.6).
- **Drop broken 1.4.1–1.4.7 edit-shell experiments**: no progressive loading, no route?.id gymnastics, no chrome class churn / `md:w-[70%]` on `admin-page-content`, no edit-only layout split.
- **Size-mode admin UI and new slot keys** (`maker_bids` / share / page) **deferred** on the ad form Select (baseline slot options only).

### Added

- **Safe image upload only** beside existing URL text inputs (`image_url`, `image_url_desktop`, `image_url_mobile`): FileUploader `autoUpload`, maxFiles 1, image accept, `POST /api/modules/custom-ad_slots/admin/uploads`, `onUploadComplete` → set `_local.form.image_url*`. **No** `files` / `value` / `image_url_*_files` props (those caused infinite load in 1.4.1–1.4.2). Delete/reorder endpoints omitted from uploader props.

### Unchanged

- Placement admin routes/layouts kept (`admin_ad_slot_placement_*`).
- Upload API (`AdSlotUploadController`), size/placement backend, migrations, and public size/slot mounts remain.

### Deploy

```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

(migrate only if uploads table still required — do not force unrelated migrations)


## [1.4.7] - 2026-09-23

### Fixed

- **수정(`/admin/ad-slots/{id}/edit`) 하드 리프레시 시 좌측 관리 메뉴 소실 + 폼 붕괴 (edit 전용):** create/list는 정상. create vs edit 슬롯 트리는 의도적 문자열(제목·POST/PUT·wrapper id)만 다르고 **FileUploader 포함 content는 동일**. 유일 상단 델타가 edit show data_source의 `loading_strategy: "progressive"`. 공식 `sirsoft-page` admin form·동일 모듈 placement edit 폼에는 progressive가 없음. progressive를 제거해 create/placement와 정렬(show GET `if/endpoint: {{route?.id}}` + `initLocal: form` + `refetchOnMount` 유지). 크롬 class/`_admin_base`·레이아웃 분리(1.4.6)는 유지.

### Unchanged

- create 레이아웃·FileUploader upload/delete noop·show API `success(msg, $payload)` / `initLocal: form` 페이로드 형상 유지.

### Changed

- Version **1.4.7**. 광고 JS `hero-carousel.js?v=1.4.7`.

### Deploy

```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```


## [1.4.6] - 2026-09-23

### Fixed

- **수정 하드 리프레시 시 좌측 관리 메뉴(`_admin_base`) 소실 (우선, 원인 좁힘):** 공식 `sirsoft-page` `admin_page_form`과 달리 광고 폼 크롬 래퍼가 `admin-page-content w-full max-w-none`이었다. `admin-page-content`에 폭 유틸(`md:w-[70%]` 등)을 붙이면 사이드바가 깨진 이력이 있어, **폭 유틸이 붙은 크롬 class를 1순위 용의**로 보고 래퍼를 공식과 동일한 `admin-page-content-responsive-fluid`만 쓰도록 교체(create/edit/placement). `w-full`/`max-w-none`/`md:w-*`를 크롬 래퍼에 두지 않음. 카드는 기존 `max-w-5xl mx-auto` 유지.
- **create+edit 공유 레이아웃이 edit 콜드로드를 create로 취급:** 공유 `init_actions`의 `if: {{!route?.id}}`가 `form: null`을 넣는데, 콜드로드에서 `route.id` 바인딩이 늦으면 show fetch/`initLocal` hydrate 전에 create 기본값이 선점한다. **레이아웃 분리:** `admin_ad_slot_form`(create 전용, show data_source 없음·기본값 init만) / `admin_ad_slot_form_edit`(edit 전용, create init **제로**, `if/endpoint: {{route?.id}}`로 show+`initLocal: form`만). 라우트 `*/admin/ad-slots/:id/edit` → edit 레이아웃.
- **렌더 시 깨질 수 있는 표현식 정리:** 에러 박스 `Object.keys(_local.errors)` → `{{!!_local.errors}}`. 저장 apiCall의 create/edit 삼항을 레이아웃별로 POST/PUT 고정.
- **FileUploader delete `:id`와 라우트 `:id` 충돌 여지:** FileUploader는 클라이언트에서 `:id`만 치환하지만, 일부 엔진이 레이아웃 문자열의 `:id`를 라우트 파라미터로 선치환할 수 있음. delete URL을 리터럴 `.../admin/uploads/noop`으로 바꿔 라우트 id를 빼앗지 않음(soft-success 유지). 서버 라우트 파라미터명 `{uploadId}`.

### Unchanged

- 이미지 업로드 POST + 삭제 시 URL 필드 비우기(onDelete/onRemove/…) 동작 유지. `files`/`value` 바인딩 재도입 없음.

### Changed

- Version **1.4.6**. 광고 JS `hero-carousel.js?v=1.4.6`.

### Deploy

```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```


## [1.4.5] - 2026-09-23

### Fixed

- **수정 페이지 하드 리프레시 시 좌측 관리 메뉴(`_admin_base`) 소실 (우선):** v1.4.4 data_source `if`/endpoint·메타/저장 분기에 `Number(...)`, `route?.params?.id`, 경로 `split('/ad-slots/')` 등 복잡한 표현식이 있어 G7 표현식 평가가 실패하면 레이아웃 마운트가 중단되고 어드민 크롬(사이드바)이 비었다. 공식 `sirsoft-page` `admin_page_form`과 동일하게 `if: "{{route?.id}}"`, `endpoint: ".../admin/ads/{{route?.id}}"`만 사용. `loading_strategy: "progressive"`로 셸이 show 응답을 기다리지 않고 마운트. 403은 content 슬롯만 에러 페이지. 래퍼 `dataKey: "form"` 제거(빈 FormContext로 크롬/폼이 리셋될 여지 차단). `extends: _admin_base` 유지.
- **수정 폼 DB 필드 미표시:** 위와 같은 `if` 실패로 show API가 호출되지 않아 `initLocal: "form"` hydrate가 안 되던 문제. 단순 `route?.id`로 show가 호출되면 `_local.form`에 DB 값이 채워짐.
- **create가 edit를 덮을 위험:** create-only `init_actions`를 `if: "{{!route?.id}}"`로 form=null → 기본값 (sirsoft-page와 동일). edit 경로에서는 init_actions 미실행.

### Unchanged

- 이미지 업로드/삭제(FileUploader upload+delete soft-success, URL 필드 단일 소스) 유지.
- show()는 `success(msg, $payload)` 유지 (`actualData = data.data ?? data`).

### Changed

- Version **1.4.5**. 광고 JS `hero-carousel.js?v=1.4.5`.

### Deploy

```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.4] - 2026-09-23

### Fixed

- **업로더 삭제 버튼 무반응:** v1.4.2에서 FileUploader `delete` 엔드포인트를 제거한 뒤 삭제 클릭이 실패하거나 URL이 남던 문제를 수정. `DELETE /admin/uploads/{id}` soft-success(실제 파일 삭제 불필요)를 복구하고, `onDelete`/`onRemove`/`onFileRemove`/`onFilesChange`(빈 목록) 시 `_local.form.image_url`·`image_url_desktop`·`image_url_mobile`을 비움. URL 필드가 단일 소스. `files`/`value` 바인딩은 재도입하지 않음(1.4.2 무한로딩 회귀 방지).
- **수정 페이지 하드 리프레시 빈 폼 + 좌측 메뉴 소실:** v1.4.3의 `route.id ?? route.params.id ?? params.id`는 `route.params`가 아직 없을 때 속성 접근으로 표현식 예외를 유발할 수 있음. 전부 `route?.params?.id ?? route?.id ?? params?.id`로 바꾸고, 경로 `/ad-slots/{id}/edit` 세그먼트 파싱 폴백을 추가해 콜드 로드에서도 숫자 id로 show API를 호출. `refetchOnMount: true`로 파라미터 지연에도 재시도.
- **중복 URL `<Img>` 미리보기 제거:** FileUploader 썸네일만 사용(업로드 직후 거대 미리보기 재발 방지). URL 텍스트 입력은 유지.

### Changed

- Version **1.4.4**. 광고 JS `hero-carousel.js?v=1.4.4`.

### Deploy

```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.3] - 2026-09-23

### Fixed

- **관리자 수정 폼 DB 미표시 (하드 리프레시):** `init_actions`가 `route?.id` 부재 시 빈 create 폼을 먼저 써서 hydrate를 덮어쓰던 경쟁 조건을 제거. 빈 폼 초기화는 `/create` 경로에서만 수행. 데이터 소스·제출·메타는 `route.id ?? route.params.id ?? params.id`로 통일 (digital_product 수정 폼과 동일).
- **하드 리프레시 시 좌측 관리 메뉴 소실:** 수정 URL에서 잘못된 create 초기화/미 fetch로 어드민 페이지 상태가 깨지던 경로를 수정. show 데이터 소스는 `loading_strategy: progressive`로 셸(사이드바) 마운트를 막지 않음.
- **이미지 URL 미리보기 과다 확대:** 레거시/데스크톱/모바일 URL `<Img>` 미리보기에 `max-h-40` + 인라인 `maxHeight:10rem`/`object-fit:contain` 제약을 적용 (Tailwind purge에도 안전). FileUploader 썸네일과 별도. 업로드→URL 채우기는 유지.

### Changed

- Version **1.4.3**. 광고 JS `hero-carousel.js?v=1.4.3`.

### Deploy

```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.2] - 2026-09-23

### Fixed

- **관리자 수정 페이지 무한로딩:** FileUploader에 존재하지 않는 delete/reorder 엔드포인트와 `files`/`value` 바인딩이 첨부 동기화로 폼 hydrate를 가로막던 문제를 수정. 업로드(POST)만 사용하고 기존 이미지는 URL 미리보기로 표시.
- **수정 폼 DB 미표시:** show API 리소스 매핑 실패(사이즈 컬럼 미마이그레이션 등) 시에도 핵심 필드로 soft-fail hydrate. `refetchOnMount` 비활성화로 로드 루프 방지.
- **새로고침 시 좌측 관리 메뉴 소실:** 70% 폭 제약을 `admin-page-content`(어드민 크롬)가 아니라 폼 카드/`max-w-5xl`에만 적용.

### Changed

- Version **1.4.2**. 광고 JS `hero-carousel.js?v=1.4.2`.

### Deploy

```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
```

## [1.4.1] - 2026-09-23

### Fixed

- **관리자 수정 폼 전체 필드 미표시:** v1.4.0에서 추가한 FileUploader가 `trackChanges` 폼을 리마운트하며 hydrate된 `_local.form`을 빈 값으로 덮어쓰던 문제를 수정. URL/제목/링크 등 모든 Input을 controlled value로 바인딩하고 `trackChanges`를 끔.
- **이미지 URL 미로드:** `image_url` / `image_url_desktop` / `image_url_mobile`이 수정 화면에서 비어 보이던 문제 수정. API에 FileUploader용 Attachment 목록(`*_files`)을 내려주고 미리보기 이미지를 표시.
- **마이그레이션 미적용 시 placements 500:** `ad_slots_placements` 테이블이 없으면 공개 placements가 내장 크기 폴백으로 동작. 아이템 size 컬럼 없으면 CRUD에서 size 필드를 제외해 500 방지.

### Changed

- Version **1.4.1**. 광고 JS `hero-carousel.js?v=1.4.1`. 관리자 라우트에서 `placements`를 `:id`보다 앞에 배치.
- **관리자 폼 폭:** 수정/등록·슬롯 크기 설정 화면을 데스크톱에서 약 70% 폭·가운데 정렬 (모바일은 100%).

### Deploy

```
php82 artisan module:update custom-ad_slots
php82 artisan cache:clear
php82 artisan view:clear
# size/placements 기능을 쓰려면(미실행 시):
php82 artisan migrate
```

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

## [1.4.0] - 2026-09-23

### Added

- **크기 모드 (ratio / fixed):** 슬롯 기본값(`ad_slots_placements`) + 광고 아이템 오버라이드. 비우면 슬롯 → 내장 폴백(히어로 `3/1`·`2/1`, 스택 원본 비율).
- **슬롯 기본 크기 관리:** 관리자 `/admin/ad-slots/placements` 및 API `admin/placements`.
- **이미지 업로드:** 관리자 폼 URL 필드 옆에 FileUploader. `POST /api/modules/custom-ad_slots/admin/uploads` (jpeg/png/gif/webp, max 5MB). 성공 시 해당 URL 필드 자동 채움.
- **신규 슬롯:** `maker_bids.top/bottom` (`/maker-bids`), `share.top/bottom` (`/share`, `/board/share`와 별개), `page.top/bottom` (`/page/*`). path-routed `cas_page_*` 마운트에 연동.

### Changed

- 목록 활성 전환 버튼 문구: **활성화하기** / **비활성하기** (상태 배지·「광고 추가」는 유지).
- Version **1.4.0**. 광고 JS `hero-carousel.js?v=1.4.0`.

### Install

```
php82 artisan module:update custom-ad_slots
php82 artisan migrate
php82 artisan cache:clear
php82 artisan view:clear
```

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

## [1.3.8] - 2026-09-11

### Fixed

- **가변 콘텐츠 폭 연동:** 상·하단 광고 컨테이너의 고정 `max-w-7xl`을 제거했습니다.
- `custom-home_design`의 `--chd-content-max-width` 값을 직접 사용하므로 관리자가 콘텐츠 최대 폭을 변경하면 상단 캐러셀과 하단 배너도 같은 폭으로 즉시 맞춰집니다.
- 광고 내부는 모두 부모 컨테이너 폭 `100%`를 사용하며 고정 픽셀 폭을 갖지 않습니다. `custom-home_design`이 없을 때만 공식 테마 폭 `80rem`을 폴백으로 사용합니다.

### Changed

- Version **1.3.8**.

## [1.3.7] - 2026-09-11

### Fixed

- **하단 배너 메인 폭 적용:** `720px` 최대 폭 제한을 제거하고 메인 콘텐츠 컨테이너의 가로폭 `100%`를 사용합니다.
- 이미지 전체를 자르지 않고 원본 비율을 유지하므로, 세로 높이는 실제 이미지 비율에 따라 자동 계산됩니다.
- 광고 JS 캐시 무효화: `hero-carousel.js?v=1.3.7`.

### Changed

- Version **1.3.7**.

## [1.3.6] - 2026-09-11

### Fixed

- **하단 배너 원본 비율 유지:** 강제 비율과 `object-fit: cover`를 제거해 이미지가 잘리지 않고 전체가 표시됩니다.
- **하단 배너 가로폭 축소:** 데스크톱에서는 최대 `720px`로 제한해 가운데 배치하고, 좁은 화면에서는 컨테이너 폭 `100%`로 반응형 표시합니다. 높이는 이미지 원본 비율에 따라 자동 계산됩니다.
- 광고 JS 캐시 무효화: `hero-carousel.js?v=1.3.6`.

### Changed

- Version **1.3.6**.

## [1.3.5] - 2026-09-11

### Fixed

- **하단 배너 크기:** 스택 배너는 가로 `100%`만 맞추고, 높이는 비율로 자동 계산합니다. 상단 히어로(`3:1` / `2:1`)보다 납작한 데스크톱 **`6:1`**, 모바일 **`3:1`** 을 씁니다.
- **비율이 무시되던 문제:** 테마 `img { height: auto }` / flex `min-height: auto`가 원본 이미지 높이로 박스를 밀어 올려, `aspect-ratio`만으로는 커 보일 수 있었습니다. 패딩 비율 잠금 + `!important` 이미지 fill로 강제합니다.
- 광고 JS 캐시 무효화: `hero-carousel.js?v=1.3.5.1`, `Cache-Control: no-store`. 구버전 스크립트가 먼저 깔려 있어도 버전 가드로 새 렌더러가 다시 붙습니다.

### Changed

- Version **1.3.5**.

### Install

```
php82 artisan module:update custom-ad_slots --source=bundled --force --layout-strategy=overwrite
php82 artisan hooks:clear
php82 artisan cache:clear
```

## [1.3.4] - 2026-09-11

### Fixed

- **하단 배너 크기 제한:** 세로 스택의 각 배너를 데스크톱 `3:1`, 모바일 `2:1` 반응형 비율로 표시하고 이미지는 영역에 맞게 채워, 원본 이미지가 세로로 길어도 홈 하단을 과도하게 차지하지 않도록 수정했습니다.

### Changed

- Version **1.3.4**.

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
