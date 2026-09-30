<?php

namespace Modules\Custom\AdSlots\Services;

/**
 * 마일리지(custom-mileage) 플러그인 연동 (1.4.29).
 *
 * custom-mileage 0.3.1 이상이 켜져 있고 이커머스 마일리지를 쓸 수 있으면
 *   - 차감 → 필터 훅 custom-mileage.spend (extension = custom-ad_slots): custom-mileage 사용 기록 · 「연동 확장」 합계 ·
 *     조정 기록 「사용 (확장)」에 남음. 부른 쪽 DB 트랜잭션 안에서 돌아 함께 되돌려짐. 같은 ref 는 한 번만 차감.
 *   - 환불 → custom-mileage.refund (차감 때와 같은 ref)
 *   - 적립 → custom-mileage.earn (custom-mileage 소멸 관리 대상 · 출처 custom-ad_slots)
 * 없거나 오래된 버전이면 null 을 돌려주고, 부른 쪽이 예전처럼 이커머스를 바로 부릅니다.
 */
final class CustomMileage
{
    public const EXTENSION = 'custom-ad_slots';

    public const LABEL = '광고 슬롯';

    private const HOOK_MANAGER = 'App\\Extension\\HookManager';

    /** custom-mileage.api 결과 (요청마다 한 번) */
    private static ?array $api = null;

    /**
     * custom-mileage 0.3.1+ 공개 API (쓸 수 없으면 빈 배열).
     *
     * @return array<string, mixed>
     */
    public static function api(): array
    {
        if (self::$api !== null) {
            return self::$api;
        }
        try {
            $hm = self::HOOK_MANAGER;
            $r = class_exists($hm) ? $hm::applyFilters('custom-mileage.api', null) : null;

            return self::$api = is_array($r) && ! empty($r['available']) ? $r : [];
        } catch (\Throwable) {
            return self::$api = [];
        }
    }

    /** 검사·상주 프로세스용: 다음 호출 때 다시 확인 */
    public static function forget(): void
    {
        self::$api = null;
    }

    /** 회원 마일리지 내역에 보일 사유 — 앞에 [광고 슬롯] (이미 [..] 로 시작하면 그대로), 200자 */
    public static function reason(string $text): string
    {
        $text = trim($text);

        return mb_substr(str_starts_with($text, '[') ? $text : '['.self::LABEL.'] '.$text, 0, 200);
    }

    /**
     * 차감. null = custom-mileage 경로를 쓸 수 없음 (예전 방식으로).
     * 결과: ['ok' => bool, 'status' => ok|insufficient|invalid|unavailable|error, 'balance', 'transaction_id', ...]
     *
     * @return array<string, mixed>|null
     */
    public static function spend(int $userId, int $amount, string $reason, ?string $ref = null, ?int $grantedBy = null): ?array
    {
        if (empty(self::api()['spend'])) {
            return null;
        }

        return self::call('custom-mileage.spend', $userId, $amount, self::reason($reason), [
            'extension' => self::EXTENSION,
            'ref' => $ref,
            'granted_by' => $grantedBy ?? $userId,
        ]);
    }

    /**
     * 환불 (spend 때와 같은 ref). null = 쓸 수 없음. status not_found = 그 경로로 차감한 기록 없음 (예전 방식으로).
     *
     * @return array<string, mixed>|null
     */
    public static function refund(string $ref, ?int $amount, string $reason): ?array
    {
        if (empty(self::api()['refund'])) {
            return null;
        }

        return self::call('custom-mileage.refund', self::EXTENSION, $ref, ['reason' => self::reason($reason), 'amount' => $amount]);
    }

    /**
     * 적립. 이커머스 거래 id, null = 처리하지 않음 (예전 방식으로).
     */
    public static function earn(int $userId, int $amount, string $reason, ?string $ref = null, ?int $grantedBy = null): ?int
    {
        if (self::api() === []) {
            return null;
        }
        try {
            $hm = self::HOOK_MANAGER;
            $r = $hm::applyFilters('custom-mileage.earn', null, $userId, $amount, self::reason($reason), [
                'extension' => self::EXTENSION,
                'ref' => $ref,
                'granted_by' => $grantedBy,
            ]);
        } catch (\Throwable) {
            return null;
        }

        return is_array($r) && isset($r['transaction_id']) ? (int) $r['transaction_id'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function call(string $hook, mixed ...$args): ?array
    {
        try {
            $hm = self::HOOK_MANAGER;
            $r = $hm::applyFilters($hook, null, ...$args);
        } catch (\Throwable $e) {
            // 차감 도중 예외 — 예전 방식으로 또 차감하지 않도록 실패로 돌려줌
            return ['ok' => false, 'status' => 'error', 'message' => $e->getMessage()];
        }

        return is_array($r) && isset($r['status']) ? $r : null;
    }
}
