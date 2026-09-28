<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdSlots\Services\AdSlotsSettingsService;
use Modules\Custom\AdSlots\Services\MileageBridge;

/**
 * 광고 슬롯 환경설정 (마일리지)
 */
class SettingsController extends AdminBaseController
{
    public function __construct(
        private AdSlotsSettingsService $settings,
        private MileageBridge $mileage,
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        return $this->success('custom-ad_slots::messages.settings.fetched', $this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mileage' => ['sometimes', 'array'],
            'mileage.click_reward_enabled' => ['sometimes', 'boolean'],
            'mileage.click_reward_amount' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'mileage.click_reward_daily_limit' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        if (isset($data['mileage']['click_reward_enabled'])) {
            $data['mileage']['click_reward_enabled'] = (bool) $data['mileage']['click_reward_enabled'];
        }
        foreach (['click_reward_amount', 'click_reward_daily_limit'] as $k) {
            if (isset($data['mileage'][$k])) {
                $data['mileage'][$k] = (int) $data['mileage'][$k];
            }
        }

        $this->settings->saveSettings($data);

        return $this->success('custom-ad_slots::messages.settings.saved', $this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return $this->settings->getAllSettings() + [
            '_status' => [
                'mileage_available' => $this->mileage->available(),
            ],
        ];
    }
}
