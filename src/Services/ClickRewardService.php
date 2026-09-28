<?php

namespace Modules\Custom\AdSlots\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Custom\AdSlots\Models\AdSlotItem;
use Modules\Custom\AdSlots\Models\AdSlotsMileageReward;

/**
 * 배너 클릭 마일리지 적립
 *
 * - 회원만, (회원, 배너, 날짜) 당 1번 → target_key "item:{id}:{Y-m-d}"
 * - 하루 적립 횟수 한도 (click_reward_daily_limit, 0 = 무제한)
 * - 설정 기본값 꺼짐. 이커머스 마일리지를 쓸 수 없으면 아무것도 하지 않음.
 * - 실패해도 클릭(이동)은 절대 막지 않음.
 */
class ClickRewardService
{
    public const ACTION = 'click_reward';

    public function __construct(
        private AdSlotsSettingsService $settings,
        private MileageBridge $mileage,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->on('mileage.click_reward_enabled', false) && $this->amount() > 0;
    }

    public function amount(): int
    {
        return $this->settings->int('mileage.click_reward_amount', 5, 0, 100000000);
    }

    public function dailyLimit(): int
    {
        return $this->settings->int('mileage.click_reward_daily_limit', 5, 0, 100000);
    }

    /**
     * @return array{awarded: bool, amount: int, reason: string}
     */
    public function reward(?int $userId, int $itemId): array
    {
        $none = fn (string $reason) => ['awarded' => false, 'amount' => 0, 'reason' => $reason];

        if (! $userId) {
            return $none('guest');
        }
        if (! $this->enabled()) {
            return $none('disabled');
        }

        try {
            $item = AdSlotItem::query()->currentlyActive()->whereKey($itemId)->first();
            if (! $item || ($item->type ?? 'static') !== 'static' || trim((string) ($item->link_url ?? '')) === '') {
                return $none('invalid_item');
            }
            if (! $this->mileage->available()) {
                return $none('unavailable');
            }

            $targetKey = 'item:'.$item->id.':'.now()->format('Y-m-d');
            $exists = AdSlotsMileageReward::query()
                ->where('user_id', $userId)->where('action', self::ACTION)->where('target_key', $targetKey)
                ->exists();
            if ($exists) {
                return $none('duplicate');
            }

            $limit = $this->dailyLimit();
            if ($limit > 0) {
                $today = AdSlotsMileageReward::query()
                    ->where('user_id', $userId)->where('action', self::ACTION)
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count();
                if ($today >= $limit) {
                    return $none('daily_limit');
                }
            }

            $amount = $this->amount();
            $title = trim((string) ($item->title ?? ''));
            $desc = '[광고] 배너 클릭 적립'.($title !== '' ? ' ('.mb_substr($title, 0, 40).')' : '');

            DB::transaction(function () use ($userId, $targetKey, $amount, $item, $desc) {
                $row = AdSlotsMileageReward::query()->create([
                    'user_id' => $userId,
                    'action' => self::ACTION,
                    'target_key' => $targetKey,
                    'amount' => $amount,
                ]);
                $txId = $this->mileage->earn($userId, $amount, '[custom-ad_slots] banner click #'.$item->id, $desc);
                if ($txId === null) {
                    throw new \RuntimeException('mileage unavailable');
                }
                if ($txId > 0) {
                    $row->transaction_id = $txId;
                    $row->save();
                }
            });

            return ['awarded' => true, 'amount' => $amount, 'reason' => 'ok'];
        } catch (QueryException $e) {
            // 동시에 두 번 눌러 unique 충돌 → 이미 적립된 것으로 처리
            if (str_contains(strtolower($e->getMessage()), 'unique') || (string) $e->getCode() === '23000') {
                return $none('duplicate');
            }
            Log::warning('[custom-ad_slots] click reward failed', ['user_id' => $userId, 'item_id' => $itemId, 'error' => $e->getMessage()]);

            return $none('error');
        } catch (\Throwable $e) {
            Log::warning('[custom-ad_slots] click reward failed', ['user_id' => $userId, 'item_id' => $itemId, 'error' => $e->getMessage()]);

            return $none('error');
        }
    }
}
