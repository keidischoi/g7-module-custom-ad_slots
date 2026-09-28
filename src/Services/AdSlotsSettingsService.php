<?php

namespace Modules\Custom\AdSlots\Services;

use App\Contracts\Extension\ModuleSettingsInterface;
use App\Support\ExtensionStoragePath;
use App\Traits\NormalizesSettingsData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * 광고 슬롯 환경설정 서비스 (custom-note 의 NoteSettingsService 와 같은 구조)
 *
 * defaults.json 은 `_meta` / `defaults` / `frontend_schema` 래퍼 구조입니다.
 * 카테고리별로 `storage/app/modules/custom-ad_slots/settings/{category}.json` 에 저장합니다.
 */
class AdSlotsSettingsService implements ModuleSettingsInterface
{
    use NormalizesSettingsData;

    public const MODULE_IDENTIFIER = 'custom-ad_slots';

    private ?array $definition = null;

    private ?array $settings = null;

    public function getSettingsDefaultsPath(): ?string
    {
        $path = dirname(__DIR__, 2).'/config/settings/defaults.json';

        return file_exists($path) ? $path : null;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->getAllSettings(), $key, $default);
    }

    public function setSetting(string $key, mixed $value): bool
    {
        $settings = $this->getAllSettings();
        Arr::set($settings, $key, $value);
        $category = explode('.', $key)[0];

        return $this->saveSettings([$category => $settings[$category] ?? []]);
    }

    public function getAllSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $defaults = $this->getDefaults();
        $settings = [];

        foreach ($defaults as $category => $categoryDefaults) {
            if (! is_array($categoryDefaults)) {
                continue;
            }
            $settings[$category] = array_merge($categoryDefaults, $this->loadCategorySettings($category));
        }

        return $this->settings = $this->normalizeSettingsData($settings, $defaults);
    }

    public function getSettings(string $category): array
    {
        return $this->getAllSettings()[$category] ?? [];
    }

    /**
     * 설정 저장 (알 수 없는 카테고리·키는 무시)
     *
     * @param  array  $settings  [category => [key => value]]
     */
    public function saveSettings(array $settings): bool
    {
        $defaults = $this->getDefaults();
        $success = true;

        foreach ($settings as $category => $categorySettings) {
            if (! is_array($categorySettings) || ! isset($defaults[$category])) {
                continue;
            }

            $categoryDefaults = $defaults[$category];
            $current = $this->getSettings($category);
            $filtered = array_intersect_key($categorySettings, $categoryDefaults);
            $merged = $this->normalizeCategoryData(array_merge($current, $filtered), $categoryDefaults);

            if (! $this->saveCategorySettings($category, $merged)) {
                $success = false;
            }
        }

        $this->clearCache();

        return $success;
    }

    public function getFrontendSettings(): array
    {
        $schema = $this->getDefinition()['frontend_schema'] ?? [];
        $all = $this->getAllSettings();
        $result = [];

        foreach ($schema as $category => $categorySchema) {
            if (! ($categorySchema['expose'] ?? false)) {
                continue;
            }
            $result[$category] = $all[$category] ?? [];
        }

        return $result;
    }

    public function clearCache(): void
    {
        $this->definition = null;
        $this->settings = null;

        if (function_exists('g7_refresh_module_settings_config')) {
            try {
                g7_refresh_module_settings_config(self::MODULE_IDENTIFIER);
            } catch (\Throwable) {
            }
        }
    }

    public function on(string $key, bool $default = false): bool
    {
        return (bool) $this->getSetting($key, $default);
    }

    public function int(string $key, int $default, int $min = 0, int $max = PHP_INT_MAX): int
    {
        $value = $this->getSetting($key, $default);

        return max($min, min($max, is_numeric($value) ? (int) $value : $default));
    }

    private function getDefinition(): array
    {
        if ($this->definition !== null) {
            return $this->definition;
        }

        $path = $this->getSettingsDefaultsPath();

        return $this->definition = $path ? (json_decode(File::get($path), true) ?? []) : [];
    }

    private function getDefaults(): array
    {
        $definition = $this->getDefinition();

        return is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [];
    }

    private function loadCategorySettings(string $category): array
    {
        $path = $this->getStoragePath().'/'.$category.'.json';

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function saveCategorySettings(string $category, array $settings): bool
    {
        $dir = $this->getStoragePath();

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return File::put($dir.'/'.$category.'.json', $json) !== false;
    }

    private function getStoragePath(): string
    {
        return ExtensionStoragePath::module(self::MODULE_IDENTIFIER, 'settings');
    }
}
