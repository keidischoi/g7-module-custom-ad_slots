<?php

namespace Modules\Custom\AdSlots\Services;

use App\Extension\ModuleManager;
use Illuminate\Support\Facades\Log;

/**
 * sirsoft-ecommerce 마일리지 연동 브리지
 *
 * 이커머스 모듈이 활성 + 마일리지 클래스 존재 + 이커머스 마일리지 설정(mileage.enabled) 켜짐일 때만 동작합니다.
 * 이커머스 클래스는 문자열로만 참조합니다 (하드 의존 없음). 사용할 수 없으면 조용히 아무것도 하지 않습니다.
 */
class MileageBridge
{
    public const MODULE = 'sirsoft-ecommerce';

    private const SERVICE = 'Modules\\Sirsoft\\Ecommerce\\Services\\UserMileageService';

    private const EARN_DTO = 'Modules\\Sirsoft\\Ecommerce\\DTO\\MileageAdminEarnDto';

    private const DEDUCT_DTO = 'Modules\\Sirsoft\\Ecommerce\\DTO\\MileageAdminDeductDto';

    /**
     * 이커머스 마일리지 사용 가능 여부.
     */
    public function available(): bool
    {
        try {
            $active = (array) app(ModuleManager::class)->getActiveModuleIdentifiers();
            if (! in_array(self::MODULE, $active, true)) {
                return false;
            }
            if (! class_exists(self::SERVICE) || ! class_exists(self::EARN_DTO) || ! class_exists(self::DEDUCT_DTO)) {
                return false;
            }

            return function_exists('module_setting')
                && (bool) module_setting(self::MODULE, 'mileage.enabled', true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 적립. 성공하면 거래 id(없으면 0), 사용할 수 없으면 null. 실패는 예외.
     */
    public function earn(int $userId, int $amount, string $memo, string $description): ?int
    {
        if ($amount <= 0 || ! $this->available()) {
            return null;
        }
        $dto = self::EARN_DTO;
        $tx = app(self::SERVICE)->adminEarn($userId, new $dto(
            amount: $amount,
            currency: $this->currency(),
            grantedBy: $userId,
            memo: $memo,
            description: $description,
        ));

        return is_object($tx) && isset($tx->id) ? (int) $tx->id : 0;
    }

    /**
     * 차감. 잔액이 모자라면 가능한 만큼만 차감하고 실제 차감액을 돌려줍니다.
     */
    public function deductUpTo(int $userId, int $amount, string $memo, string $description): int
    {
        if ($amount <= 0 || ! $this->available()) {
            return 0;
        }
        $take = min($amount, max(0, (int) $this->balance($userId)));
        if ($take <= 0) {
            return 0;
        }
        $dto = self::DEDUCT_DTO;
        try {
            app(self::SERVICE)->adminDeduct($userId, new $dto(
                amount: $take,
                currency: $this->currency(),
                grantedBy: $userId,
                memo: $memo,
                description: $description,
            ));

            return $take;
        } catch (\Throwable $e) {
            Log::warning('[custom-ad_slots] mileage deduct failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    public function balance(int $userId): ?int
    {
        if (! $this->available()) {
            return null;
        }
        try {
            $service = app(self::SERVICE);
            if (method_exists($service, 'availableBalance')) {
                return (int) $service->availableBalance($userId, $this->currency());
            }
            $b = $service->getBalance($userId);

            return (int) (is_array($b) ? ($b['available'] ?? 0) : $b);
        } catch (\Throwable) {
            return null;
        }
    }

    public function currency(): string
    {
        try {
            if (function_exists('module_setting')) {
                $c = trim((string) module_setting(self::MODULE, 'language_currency.default_currency', ''));
                if ($c !== '') {
                    return $c;
                }
                $rules = (array) module_setting(self::MODULE, 'mileage.currency_rules', []);
                $first = is_array($rules[0] ?? null) ? $rules[0] : [];
                $c = trim((string) ($first['currency_code'] ?? $first['currency'] ?? ''));
                if ($c !== '') {
                    return $c;
                }
            }
        } catch (\Throwable) {
        }

        return 'KRW';
    }
}
