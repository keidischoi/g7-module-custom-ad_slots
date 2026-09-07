<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdSlots\Http\Requests\Admin\StoreAdSlotItemRequest;
use Modules\Custom\AdSlots\Http\Requests\Admin\UpdateAdSlotItemRequest;
use Modules\Custom\AdSlots\Http\Resources\AdSlotItemResource;
use Modules\Custom\AdSlots\Models\AdSlotItem;
use Modules\Custom\AdSlots\Services\AdSlotService;

/**
 * 관리자 광고 슬롯 CRUD + toggle
 *
 * 미들웨어는 routes/api.php 에서 permission:admin,custom-ad_slots.ads.* 스타일로 지정
 * (G7 hello_module 과 동일 패턴).
 */
class AdSlotItemController extends AdminBaseController
{
    public function __construct(
        private AdSlotService $adSlotService,
    ) {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->adSlotService->paginateAdmin($request->only([
                'slot_key',
                'type',
                'is_active',
                'per_page',
            ]));

            return $this->success('custom-ad_slots::messages.ad.fetch_success', [
                'data' => AdSlotItemResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'slot_keys' => AdSlotItem::SLOT_KEYS,
            ]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.fetch_failed', 500, $e->getMessage());
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $item = $this->adSlotService->findOrFail($id);

            return $this->success(
                'custom-ad_slots::messages.ad.fetch_success',
                (new AdSlotItemResource($item))->resolve()
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.ad.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.fetch_failed', 500, $e->getMessage());
        }
    }

    public function store(StoreAdSlotItemRequest $request): JsonResponse
    {
        try {
            $item = $this->adSlotService->create($request->validated());

            return $this->success(
                'custom-ad_slots::messages.ad.create_success',
                (new AdSlotItemResource($item))->resolve(),
                201
            );
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.create_failed', 500, $e->getMessage());
        }
    }

    public function update(UpdateAdSlotItemRequest $request, int $id): JsonResponse
    {
        try {
            $item = $this->adSlotService->findOrFail($id);
            $item = $this->adSlotService->update($item, $request->validated());

            return $this->success(
                'custom-ad_slots::messages.ad.update_success',
                (new AdSlotItemResource($item))->resolve()
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.ad.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.update_failed', 500, $e->getMessage());
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $item = $this->adSlotService->findOrFail($id);
            $this->adSlotService->delete($item);

            return $this->success('custom-ad_slots::messages.ad.delete_success');
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.ad.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.delete_failed', 500, $e->getMessage());
        }
    }

    /**
     * is_active 토글
     */
    public function toggle(int $id): JsonResponse
    {
        try {
            $item = $this->adSlotService->findOrFail($id);
            $item = $this->adSlotService->toggle($item);

            return $this->success(
                'custom-ad_slots::messages.ad.toggle_success',
                (new AdSlotItemResource($item))->resolve()
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.ad.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.toggle_failed', 500, $e->getMessage());
        }
    }
}
