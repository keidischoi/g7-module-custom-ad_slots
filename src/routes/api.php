<?php

use Illuminate\Support\Facades\Route;
use Modules\Custom\AdSlots\Http\Controllers\Admin\AdSlotItemController;
use Modules\Custom\AdSlots\Http\Controllers\Public\PlacementController;

/*
|--------------------------------------------------------------------------
| custom-ad_slots API Routes
|--------------------------------------------------------------------------
|
| ModuleRouteServiceProvider 가 자동으로 prefix 를 적용합니다.
| - URL prefix: 'api/modules/custom-ad_slots'
| - Name prefix: 'api.modules.custom-ad_slots.'
|
| 권한 미들웨어 스타일 (G7 hello_module 검증):
|   permission:admin,custom-ad_slots.ads.read
|   permission:admin,custom-ad_slots.ads.create
|   permission:admin,custom-ad_slots.ads.update
|   permission:admin,custom-ad_slots.ads.delete
|
*/

/*
| 공개 placements — 비로그인 접근 가능
| GET /api/modules/custom-ad_slots/placements
| GET /api/modules/custom-ad_slots/placements?slot=home.top
*/
Route::prefix('placements')
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('placements.')
    ->group(function () {
        Route::get('/', [PlacementController::class, 'index'])->name('index');
    });

/*
| 관리자 CRUD + toggle
| /api/modules/custom-ad_slots/admin/ads
*/
Route::prefix('admin/ads')
    ->middleware(['auth:sanctum', 'throttle:600,1'])
    ->name('admin.ads.')
    ->group(function () {
        Route::get('/', [AdSlotItemController::class, 'index'])
            ->middleware('permission:admin,custom-ad_slots.ads.read')
            ->name('index');

        Route::post('/', [AdSlotItemController::class, 'store'])
            ->middleware('permission:admin,custom-ad_slots.ads.create')
            ->name('store');

        Route::get('/{id}', [AdSlotItemController::class, 'show'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_slots.ads.read')
            ->name('show');

        Route::put('/{id}', [AdSlotItemController::class, 'update'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_slots.ads.update')
            ->name('update');

        Route::patch('/{id}/toggle', [AdSlotItemController::class, 'toggle'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_slots.ads.update')
            ->name('toggle');

        Route::delete('/{id}', [AdSlotItemController::class, 'destroy'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_slots.ads.delete')
            ->name('destroy');
    });
