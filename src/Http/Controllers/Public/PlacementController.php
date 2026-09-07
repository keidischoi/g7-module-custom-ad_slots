<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Public;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdSlots\Http\Resources\AdSlotItemResource;
use Modules\Custom\AdSlots\Models\AdSlotItem;
use Modules\Custom\AdSlots\Services\AdSlotService;

/**
 * 공개 placements API
 *
 * GET /api/modules/custom-ad_slots/placements
 * GET /api/modules/custom-ad_slots/placements?slot=home.top
 */
class PlacementController extends PublicBaseController
{
    public function __construct(
        private AdSlotService $adSlotService,
    ) {
        parent::__construct();
    }

    /**
     * 활성 + 스케줄 윈도우 내 placements 조회.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $slot = $request->query('slot');
            $slot = is_string($slot) ? trim($slot) : null;

            if ($slot !== null && $slot !== '' && ! in_array($slot, AdSlotItem::SLOT_KEYS, true)) {
                return $this->error('custom-ad_slots::messages.placement.invalid_slot', 422, [
                    'slot' => $slot,
                    'allowed' => AdSlotItem::SLOT_KEYS,
                ]);
            }

            $result = $this->adSlotService->getPublicPlacements($slot ?: null);

            if ($slot !== null && $slot !== '') {
                $payload = AdSlotItemResource::collection($result)->resolve();
            } else {
                $payload = [];
                foreach ($result as $slotKey => $items) {
                    $payload[$slotKey] = AdSlotItemResource::collection($items)->resolve();
                }
            }

            return $this->success('custom-ad_slots::messages.placement.fetch_success', $payload);
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.placement.fetch_failed', 500, $e->getMessage());
        }
    }
}
