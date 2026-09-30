<?php

namespace Modules\Custom\AdSlots\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Custom\AdSlots\Services\CustomMileage;

/**
 * custom-mileage(0.3.1+) 관리자 「연동 확장」 목록에 광고 슬롯을(를) 등록합니다 (필터 custom-mileage.integrations, 1.4.29).
 *
 * 금액·켜고 끄기는 이 확장의 설정에 그대로 둡니다 (custom-mileage 는 링크와 사용·환불 합계만 보여 줌).
 * custom-mileage 가 없거나 오래된 버전이면 이 필터는 불리지 않을 뿐입니다.
 */
class MileageIntegrationListener implements HookListenerInterface
{
    public const SETTINGS_URL = '/admin/ad-slots/settings';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'custom-mileage.integrations' => ['method' => 'register', 'priority' => 10, 'type' => 'filter'],
        ];
    }

    /**
     * @param  mixed  ...$args  훅 인자
     */
    public function handle(...$args): void {}

    public function register(mixed $list = []): array
    {
        $list = is_array($list) ? $list : [];
        foreach ($list as $item) {
            if (is_array($item) && ($item['identifier'] ?? '') === CustomMileage::EXTENSION) {
                return $list;
            }
        }
        $list[] = self::entry();

        return $list;
    }

    /**
     * @return array{identifier: string, name: string, description: string, settings_url: string, kind: string}
     */
    public static function entry(): array
    {
        return [
            'identifier' => CustomMileage::EXTENSION,
            'name' => CustomMileage::LABEL,
            'description' => '배너 클릭 적립 (광고 슬롯 설정)',
            'settings_url' => self::SETTINGS_URL,
            'kind' => 'module',
        ];
    }
}
