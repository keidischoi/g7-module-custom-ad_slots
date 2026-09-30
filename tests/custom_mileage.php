<?php

/**
 * 마일리지(custom-mileage) 연동 검사 (1.4.29) — Laravel 없이, 가짜 HookManager·이커머스:
 *   php tests/custom_mileage.php
 *
 * - custom-mileage 0.3.1+ 가 있으면 차감은 custom-mileage.spend (extension = custom-ad_slots), 적립은 custom-mileage.earn
 * - 없거나 오래된 버전·쓸 수 없으면 예전처럼 이커머스 adminDeduct / adminEarn
 * - 「연동 확장」 등록 리스너 · 모듈/플러그인 등록 · 버전
 */

namespace App\Contracts\Extension {
    interface HookListenerInterface
    {
    }
}

namespace App\Extension {
    final class HookManager
    {
        /** @var array<string, callable> */
        public static array $filters = [];

        public static function applyFilters(string $name, mixed $value = null, mixed ...$args): mixed
        {
            return isset(self::$filters[$name]) ? (self::$filters[$name])($value, ...$args) : $value;
        }
    }

    class ModuleManager
    {
        public static function getActiveModuleIdentifiers(): array
        {
            return ['sirsoft-ecommerce'];
        }

        public function getModule(string $id): ?object
        {
            return $id === 'sirsoft-ecommerce' ? new \stdClass() : null;
        }
    }
}

namespace Illuminate\Support\Facades {
    final class Log
    {
        public static function warning(string $m, array $c = []): void {}

        public static function info(string $m, array $c = []): void {}
    }
}

namespace Modules\Sirsoft\Ecommerce\DTO {
    class MileageAdminEarnDto
    {
        public function __construct(public int $amount, public string $currency, public int $grantedBy, public ?string $memo = null, public ?string $description = null) {}
    }

    class MileageAdminDeductDto
    {
        public function __construct(public int $amount, public string $currency, public int $grantedBy, public ?string $memo = null, public ?string $description = null) {}
    }
}

namespace Modules\Sirsoft\Ecommerce\Services {
    class UserMileageService
    {
        public array $bal = [];

        public array $log = [];

        public function availableBalance(int $u, ?string $c = null): int
        {
            return $this->bal[$u] ?? 0;
        }

        public function adminDeduct(int $u, object $dto): object
        {
            $this->log[] = ['deduct', $u, $dto];
            $this->bal[$u] = ($this->bal[$u] ?? 0) - $dto->amount;

            return (object) ['id' => 500 + count($this->log)];
        }

        public function adminEarn(int $u, object $dto): object
        {
            $this->log[] = ['earn', $u, $dto];
            $this->bal[$u] = ($this->bal[$u] ?? 0) + $dto->amount;

            return (object) ['id' => 500 + count($this->log)];
        }
    }
}

namespace {
    use App\Extension\HookManager;
    use Modules\Sirsoft\Ecommerce\Services\UserMileageService;

    $root = dirname(__DIR__);
    $ecom = new UserMileageService();

    function app(string $c): object
    {
        global $ecom;

        return str_contains($c, 'ModuleManager') ? new App\Extension\ModuleManager() : $ecom;
    }

    function module_setting(string $m, string $k, mixed $d = null): mixed
    {
        return $k === 'mileage.enabled' ? true : $d;
    }

    function __(string $k, array $p = []): string
    {
        return $k;
    }

    $fails = 0;
    $count = 0;
    function check(bool $ok, string $label): void
    {
        global $fails, $count;
        $count++;
        if (! $ok) {
            $fails++;
            echo "FAIL: {$label}\n";
        }
    }

    function expect_throw(callable $f, callable $test, string $label): void
    {
        try {
            $f();
            check(false, $label.' (예외 없음)');
        } catch (\Throwable $e) {
            check($test($e), $label.' ('.get_class($e).': '.$e->getMessage().')');
        }
    }

    /** 가짜 custom-mileage 0.3.1: 이커머스(가짜) 잔액을 그대로 줄이고 늘림 */
    final class FakeCustomMileage
    {
        public static array $spends = [];

        public static array $earns = [];

        public static function install(): void
        {
            global $ecom;
            self::$spends = [];
            self::$earns = [];
            HookManager::$filters['custom-mileage.api'] = static fn () => ['version' => '0.3.2', 'spend' => true, 'refund' => true, 'available' => true];
            HookManager::$filters['custom-mileage.spend'] = static function ($v, $uid, $amount, $reason, $o) use ($ecom) {
                $k = $o['extension'].'|'.($o['ref'] ?? uniqid('', true));
                if (isset(self::$spends[$k])) {
                    return ['ok' => true, 'status' => 'ok', 'duplicate' => true, 'transaction_id' => self::$spends[$k]['tx']];
                }
                if (($ecom->bal[$uid] ?? 0) < $amount) {
                    return ['ok' => false, 'status' => 'insufficient', 'balance' => $ecom->bal[$uid] ?? 0];
                }
                $ecom->bal[$uid] -= $amount;
                $tx = 900 + count(self::$spends);
                self::$spends[$k] = ['user' => $uid, 'amount' => $amount, 'reason' => $reason, 'opts' => $o, 'tx' => $tx, 'refunded' => null];

                return ['ok' => true, 'status' => 'ok', 'amount' => $amount, 'balance' => $ecom->bal[$uid], 'transaction_id' => $tx, 'duplicate' => false];
            };
            HookManager::$filters['custom-mileage.refund'] = static function ($v, $ext, $ref, $o) use ($ecom) {
                $k = $ext.'|'.$ref;
                if (! isset(self::$spends[$k])) {
                    return ['ok' => false, 'status' => 'not_found'];
                }
                $amount = min(self::$spends[$k]['amount'], (int) ($o['amount'] ?? self::$spends[$k]['amount']));
                $ecom->bal[self::$spends[$k]['user']] += $amount;
                self::$spends[$k]['refunded'] = $o + ['refunded_amount' => $amount];

                return ['ok' => true, 'status' => 'ok', 'amount' => $amount];
            };
            HookManager::$filters['custom-mileage.earn'] = static function ($v, $uid, $amount, $reason, $o) use ($ecom) {
                $ecom->bal[$uid] = ($ecom->bal[$uid] ?? 0) + $amount;
                self::$earns[] = ['user' => $uid, 'amount' => $amount, 'reason' => $reason, 'opts' => $o];

                return ['transaction_id' => 700 + count(self::$earns), 'expires_at' => null];
            };
        }
    }

    function reset_all(array $bal = [1 => 500, 2 => 0]): void
    {
        global $ecom;
        $ecom->bal = $bal;
        $ecom->log = [];
        HookManager::$filters = [];
        FakeCustomMileage::$spends = [];
        FakeCustomMileage::$earns = [];
        \Modules\Custom\AdSlots\Services\CustomMileage::forget();
    }

    require $root.'/src/Services/CustomMileage.php';
    require $root.'/src/Services/MileageBridge.php';
    require $root.'/src/Listeners/MileageIntegrationListener.php';

    // ---- 공통: CustomMileage
    reset_all();
    check(\Modules\Custom\AdSlots\Services\CustomMileage::api() === [] && \Modules\Custom\AdSlots\Services\CustomMileage::spend(1, 10, 'x') === null && \Modules\Custom\AdSlots\Services\CustomMileage::earn(1, 10, 'x') === null && \Modules\Custom\AdSlots\Services\CustomMileage::refund('r', 1, 'x') === null, 'custom-mileage 없음 → 모두 null (예전 방식)');
    HookManager::$filters['custom-mileage.api'] = static fn () => ['spend' => true, 'refund' => true, 'available' => false];
    check(\Modules\Custom\AdSlots\Services\CustomMileage::api() === [], '이커머스를 쓸 수 없는 custom-mileage → 쓰지 않음');
    reset_all();
    FakeCustomMileage::install();
    check(\Modules\Custom\AdSlots\Services\CustomMileage::reason('보상') === '[광고 슬롯] 보상' && \Modules\Custom\AdSlots\Services\CustomMileage::reason('[이미] 있음') === '[이미] 있음' && mb_strlen(\Modules\Custom\AdSlots\Services\CustomMileage::reason(str_repeat('가', 300))) === 200, '사유: [광고 슬롯] 접두사 · 200자');
    $r = \Modules\Custom\AdSlots\Services\CustomMileage::spend(1, 30, '열람', 'k:1', 9);
    $s = FakeCustomMileage::$spends['custom-ad_slots|k:1'] ?? null;
    check(is_array($r) && $r['ok'] && $s !== null && $s['opts']['extension'] === 'custom-ad_slots' && $s['opts']['granted_by'] === 9 && $s['reason'] === '[광고 슬롯] 열람', 'spend: extension · ref · granted_by · 사유');
    check(\Modules\Custom\AdSlots\Services\CustomMileage::refund('k:1', null, '돌려줌')['ok'] === true && FakeCustomMileage::$spends['custom-ad_slots|k:1']['refunded']['reason'] === '[광고 슬롯] 돌려줌', 'refund: 같은 ref');
    check(\Modules\Custom\AdSlots\Services\CustomMileage::earn(2, 7, '보상', 'x:1') > 0 && end(FakeCustomMileage::$earns)['opts']['extension'] === 'custom-ad_slots', 'earn: 거래 id · extension');
    HookManager::$filters['custom-mileage.spend'] = static function () {
        throw new RuntimeException('boom');
    };
    check(\Modules\Custom\AdSlots\Services\CustomMileage::spend(1, 5, 'x')['status'] === 'error', 'spend 훅 예외 → error (예전 방식으로 또 차감하지 않음)');
    HookManager::$filters['custom-mileage.spend'] = static fn ($v) => $v;
    check(\Modules\Custom\AdSlots\Services\CustomMileage::spend(1, 5, 'x') === null, 'spend 훅 미처리(null) → null (예전 방식)');
    HookManager::$filters['custom-mileage.earn'] = static fn ($v) => $v;
    check(\Modules\Custom\AdSlots\Services\CustomMileage::earn(1, 5, 'x') === null, 'earn 훅 미처리 → null (예전 방식)');

    // ---- 공통: 「연동 확장」 등록
    $l = new \Modules\Custom\AdSlots\Listeners\MileageIntegrationListener();
    $list = $l->register([]);
    check(count($list) === 1 && $list[0]['identifier'] === 'custom-ad_slots' && $list[0]['name'] === '광고 슬롯' && $list[0]['kind'] === 'module', '연동 확장 등록');
    check(preg_match('/^\/[^\/\s"\'<>\\\\]/', $list[0]['settings_url']) === 1, '연동 확장: 사이트 안 주소');
    check(count($l->register($list)) === 1 && count($l->register(null)) === 1, '연동 확장: 중복 없음 · 배열 아니면 새로');
    check(\Modules\Custom\AdSlots\Listeners\MileageIntegrationListener::getSubscribedHooks()['custom-mileage.integrations']['type'] === 'filter', '연동 확장: 필터 훅');
    check(str_contains((string) file_get_contents($root.'/module.php'), 'MileageIntegrationListener::class'), 'module.php: 리스너 등록');
    $man = json_decode((string) file_get_contents($root.'/module.json'), true);
    $comp = is_file($root.'/composer.json') ? json_decode((string) file_get_contents($root.'/composer.json'), true) : [];
    check(($man['version'] ?? '') === '1.4.29' && (! isset($comp['version']) || $comp['version'] === '1.4.29'), '버전 1.4.29');

    // ---- 이 확장의 브리지
    $b = new Modules\Custom\AdSlots\Services\MileageBridge();
    reset_all();
    check($b->earn(2, 10, '[memo] click #1', '배너 클릭 보상') > 500 && $ecom->log[0][0] === 'earn' && $ecom->log[0][2]->memo === '[memo] click #1', '없음: 적립 = 이커머스 (메모 그대로)');
    check($b->deductUpTo(2, 50, '[memo]', '회수') === 10 && end($ecom->log)[0] === 'deduct', '없음: 회수 = 이커머스');
    reset_all();
    FakeCustomMileage::install();
    check($b->earn(2, 10, '[memo] click #1', '배너 클릭 보상') > 700 && $ecom->log === [] && FakeCustomMileage::$earns[0]['reason'] === '[광고 슬롯] 배너 클릭 보상', '있음: 적립 = custom-mileage.earn (사유 = 회원에게 보이는 설명)');
    check($b->deductUpTo(2, 50, '[memo]', '회수') === 10 && $ecom->log === [] && count(FakeCustomMileage::$spends) === 1, '있음: 회수 = custom-mileage.spend');
    HookManager::$filters['custom-mileage.spend'] = static fn () => ['ok' => false, 'status' => 'error'];
    $ecom->bal[2] = 10;
    check($b->deductUpTo(2, 5, '[memo]', '회수') === 0 && $ecom->bal[2] === 10, '회수 실패 → 0');

    echo ($fails === 0 ? 'OK' : 'FAILED')." ({$count} checks, {$fails} failed)\n";
    exit($fails === 0 ? 0 : 1);
}
