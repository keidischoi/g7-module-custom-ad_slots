<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Custom\AdSlots\Http\Requests\Admin\UpdateAdSlotPlacementRequest;
use Modules\Custom\AdSlots\Http\Resources\AdSlotPlacementResource;
use Modules\Custom\AdSlots\Services\AdSlotService;

/**
 * Admin CRUD for slot-level size defaults.
 */
class AdSlotPlacementController extends AdminBaseController
{
    public function __construct(
        private AdSlotService $adSlotService,
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        try {
            $rows = $this->adSlotService->listPlacements();

            return $this->success('custom-ad_slots::messages.placement.fetch_success', [
                'data' => AdSlotPlacementResource::collection($rows)->resolve(),
            ]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.placement.fetch_failed', 500, $e->getMessage());
        }
    }

    public function show(string $slotKey): JsonResponse
    {
        try {
            $row = $this->adSlotService->findPlacementOrFail($slotKey);

            return $this->success(
                'custom-ad_slots::messages.placement.fetch_success',
                (new AdSlotPlacementResource($row))->resolve()
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.placement.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.placement.fetch_failed', 500, $e->getMessage());
        }
    }

    public function update(UpdateAdSlotPlacementRequest $request, string $slotKey): JsonResponse
    {
        try {
            $row = $this->adSlotService->findPlacementOrFail($slotKey);
            $row = $this->adSlotService->updatePlacement($row, $request->validated());

            return $this->success(
                'custom-ad_slots::messages.placement.update_success',
                (new AdSlotPlacementResource($row))->resolve()
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.placement.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.placement.update_failed', 500, $e->getMessage());
        }
    }
}
