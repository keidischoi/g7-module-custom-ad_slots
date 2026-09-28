<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Public;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdSlots\Services\ClickRewardService;

/**
 * 배너 클릭 마일리지 적립
 *
 * POST /api/modules/custom-ad_slots/ads/{id}/click (optional.sanctum)
 * 비회원·설정 꺼짐·한도 초과여도 오류 없이 awarded=false 로 응답합니다.
 */
class ClickRewardController extends PublicBaseController
{
    public function __construct(
        private ClickRewardService $rewards,
    ) {
        parent::__construct();
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $result = $this->rewards->reward($user ? (int) $user->id : null, $id);

        return $this->success(
            $result['awarded'] ? 'custom-ad_slots::messages.click_reward.awarded' : 'custom-ad_slots::messages.click_reward.skipped',
            $result,
            200,
            ['amount' => number_format($result['amount'])]
        );
    }
}
