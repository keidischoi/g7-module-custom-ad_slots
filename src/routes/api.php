<?php

use Illuminate\Support\Facades\Route;
use Modules\Custom\AdSlots\Http\Controllers\Admin\AdSlotItemController;
use Modules\Custom\AdSlots\Http\Controllers\Public\PlacementController;

/*
| ModuleRouteServiceProvider prefix: api/modules/custom-ad_slots
|
| Public placements — no auth (same style as sirsoft-ecommerce settings/*)
| Admin CRUD — sanctum + permission
*/

Route::get('placements', [PlacementController::class, 'index'])
    ->middleware(['throttle:600,1'])
    ->name('placements.index');

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

        Route::post('/{id}/duplicate', [AdSlotItemController::class, 'duplicate'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_slots.ads.create')
            ->name('duplicate');

        Route::patch('/{id}/toggle', [AdSlotItemController::class, 'toggle'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_slots.ads.update')
            ->name('toggle');

        Route::delete('/{id}', [AdSlotItemController::class, 'destroy'])
            ->whereNumber('id')
            ->middleware('permission:admin,custom-ad_slots.ads.delete')
            ->name('destroy');
    });
