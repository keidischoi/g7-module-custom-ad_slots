<?php

namespace Modules\Custom\AdSlots\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Store / delete ad images for URL fields (desktop/mobile/legacy).
 *
 * Prefers G7 StorageInterface (images category); falls back to public disk.
 * File ids are base64url(relative path) so destroy can remove the blob.
 * Also remembers last upload URL per admin user + field (session primary, cache
 * fallback — digital_product temp_key equivalent) so create/update can persist
 * URLs even if the form body races.
 */
class AdSlotUploadService
{
    private const CATEGORY = 'images';

    private const DIR = 'custom-ad_slots';

    /**
     * @return array{
     *     id: string,
     *     hash: string,
     *     original_filename: string,
     *     mime_type: string,
     *     size: int,
     *     size_formatted: string,
     *     download_url: string,
     *     url: string,
     *     thumbnail_url: string,
     *     order: int,
     *     is_image: bool,
     *     path: string,
     *     name: string
     * }
     */
    public function storeImage(UploadedFile $file): array
    {
        $ext = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $hash = (string) Str::uuid();
        $storedFilename = $hash.'.'.$ext;
        $datePath = now()->format('Y/m/d');
        $relative = self::DIR.'/'.$datePath.'/'.$storedFilename;

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new \RuntimeException('Failed to read uploaded file.');
        }

        $url = $this->putAndUrl($relative, $contents);

        $size = (int) $file->getSize();
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $original = (string) $file->getClientOriginalName();

        return [
            'id' => self::encodeId($relative),
            'hash' => $hash,
            'original_filename' => $original,
            'name' => $original,
            'mime_type' => $mime,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'download_url' => $url,
            'url' => $url,
            'thumbnail_url' => $url,
            'order' => 0,
            'is_image' => true,
            'path' => $relative,
        ];
    }

    /**
     * Delete a stored image by FileUploader id (base64url path) or soft-no-op.
     *
     * @return array{id: string, deleted: bool, path: ?string}
     */
    public function deleteByUploadId(int|string $uploadId): array
    {
        $raw = trim(urldecode((string) $uploadId));
        if ($raw === '' || strcasecmp($raw, 'noop') === 0) {
            return ['id' => $raw !== '' ? $raw : 'noop', 'deleted' => false, 'path' => null];
        }

        $path = self::decodeId($raw);
        if ($path === null || ! self::isManagedPath($path)) {
            // External / unknown id — soft success (client clears URL).
            return ['id' => $raw, 'deleted' => false, 'path' => null];
        }

        $deleted = $this->deletePath($path);

        return ['id' => $raw, 'deleted' => $deleted, 'path' => $path];
    }


    public const URL_FIELDS = ['image_url', 'image_url_desktop', 'image_url_mobile'];

    private const REMEMBER_TTL = 1800;

    public static function newUploadToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function isUrlField(string $field): bool
    {
        return in_array($field, self::URL_FIELDS, true);
    }

    public static function rememberUrl(int|string $userId, string $field, string $url, ?string $token = null): void
    {
        if (! self::isUrlField($field)) {
            return;
        }
        $url = trim($url);
        if ($url === '') {
            return;
        }
        $token = self::normalizeToken($token);

        // Clear "cleared" markers for this field (token + legacy).
        self::sessionForget(self::clearedKey($userId, $field, $token));
        self::sessionForget(self::clearedKey($userId, $field, null));
        try {
            Cache::forget(self::clearedKey($userId, $field, $token));
            Cache::forget(self::clearedKey($userId, $field, null));
        } catch (\Throwable) {
        }

        // Token-scoped remember (maker_bids upload_token pattern) + legacy user+field.
        if ($token !== null) {
            self::sessionPut(self::rememberKey($userId, $field, $token), $url);
            try {
                Cache::put(self::rememberKey($userId, $field, $token), $url, self::REMEMBER_TTL);
            } catch (\Throwable) {
            }
        }
        self::sessionPut(self::rememberKey($userId, $field, null), $url);
        try {
            Cache::put(self::rememberKey($userId, $field, null), $url, self::REMEMBER_TTL);
        } catch (\Throwable) {
        }
    }

    public static function forgetUrl(int|string $userId, string $field, ?string $token = null): void
    {
        if (! self::isUrlField($field)) {
            return;
        }
        $token = self::normalizeToken($token);

        self::sessionForget(self::rememberKey($userId, $field, $token));
        self::sessionForget(self::rememberKey($userId, $field, null));
        self::sessionPut(self::clearedKey($userId, $field, $token), true);
        self::sessionPut(self::clearedKey($userId, $field, null), true);
        try {
            Cache::forget(self::rememberKey($userId, $field, $token));
            Cache::forget(self::rememberKey($userId, $field, null));
            Cache::put(self::clearedKey($userId, $field, $token), true, self::REMEMBER_TTL);
            Cache::put(self::clearedKey($userId, $field, null), true, self::REMEMBER_TTL);
        } catch (\Throwable) {
        }
    }

    public static function peekRememberedUrl(int|string $userId, string $field, ?string $token = null): ?string
    {
        if (! self::isUrlField($field)) {
            return null;
        }
        $token = self::normalizeToken($token);

        if ($token !== null) {
            $url = self::readRemember($userId, $field, $token);
            if ($url !== null) {
                return $url;
            }
        }

        return self::readRemember($userId, $field, null);
    }

    public static function forgetAllUrls(int|string $userId, ?string $token = null): void
    {
        foreach (self::URL_FIELDS as $field) {
            self::forgetUrl($userId, $field, $token);
        }
    }

    /**
     * Fill empty image_url* from token-scoped (preferred) or legacy remembered uploads.
     * maker_bids equivalent of claimToken → image_url* columns (no files table).
     * Non-empty request values win. Cleared markers win over remember.
     * Strips upload_token from the returned array so it is not persisted.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeRememberedUrls(int|string $userId, array $data): array
    {
        $token = self::normalizeToken(
            isset($data['upload_token']) && is_string($data['upload_token'])
                ? $data['upload_token']
                : (isset($data['token']) && is_string($data['token']) ? $data['token'] : null)
        );
        unset($data['upload_token'], $data['token']);

        foreach (self::URL_FIELDS as $field) {
            $current = $data[$field] ?? null;

            $cleared = self::sessionPull(self::clearedKey($userId, $field, $token));
            $cleared = $cleared || self::sessionPull(self::clearedKey($userId, $field, null));
            try {
                $cleared = $cleared || Cache::pull(self::clearedKey($userId, $field, $token));
                $cleared = $cleared || Cache::pull(self::clearedKey($userId, $field, null));
            } catch (\Throwable) {
            }
            if ($cleared) {
                self::dropRemember($userId, $field, $token);
                continue;
            }

            if (is_string($current) && trim($current) !== '') {
                self::dropRemember($userId, $field, $token);
                continue;
            }

            $remembered = self::peekRememberedUrl($userId, $field, $token);
            if (is_string($remembered) && $remembered !== '') {
                $data[$field] = $remembered;
                self::dropRemember($userId, $field, $token);
            }
        }

        return $data;
    }

    private static function normalizeToken(?string $token): ?string
    {
        if (! is_string($token)) {
            return null;
        }
        $token = trim($token);
        if ($token === '' || strlen($token) > 128) {
            return null;
        }
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            return null;
        }

        return $token;
    }

    private static function readRemember(int|string $userId, string $field, ?string $token): ?string
    {
        $url = self::sessionGet(self::rememberKey($userId, $field, $token));
        if (! is_string($url) || trim($url) === '') {
            try {
                $url = Cache::get(self::rememberKey($userId, $field, $token));
            } catch (\Throwable) {
                $url = null;
            }
        }

        return is_string($url) && trim($url) !== '' ? trim($url) : null;
    }

    private static function dropRemember(int|string $userId, string $field, ?string $token): void
    {
        self::sessionForget(self::rememberKey($userId, $field, $token));
        self::sessionForget(self::rememberKey($userId, $field, null));
        try {
            Cache::forget(self::rememberKey($userId, $field, $token));
            Cache::forget(self::rememberKey($userId, $field, null));
        } catch (\Throwable) {
        }
    }

    private static function rememberKey(int|string $userId, string $field, ?string $token = null): string
    {
        if ($token !== null) {
            return 'custom-ad_slots:upload:'.(string) $userId.':'.$token.':'.$field;
        }

        return 'custom-ad_slots:last_upload:'.(string) $userId.':'.$field;
    }

    private static function clearedKey(int|string $userId, string $field, ?string $token = null): string
    {
        if ($token !== null) {
            return 'custom-ad_slots:upload_cleared:'.(string) $userId.':'.$token.':'.$field;
        }

        return 'custom-ad_slots:upload_cleared:'.(string) $userId.':'.$field;
    }

    private static function sessionPut(string $key, mixed $value): void
    {
        try {
            Session::put($key, $value);
        } catch (\Throwable) {
        }
    }

    private static function sessionGet(string $key): mixed
    {
        try {
            return Session::get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function sessionForget(string $key): void
    {
        try {
            Session::forget($key);
        } catch (\Throwable) {
        }
    }

    private static function sessionPull(string $key): mixed
    {
        try {
            return Session::pull($key);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build a one-item FileUploader initialFiles list from a public URL.
     *
     * @return list<array<string, mixed>>
     */
    public static function uploaderFilesFromUrl(?string $url): array
    {
        if (! is_string($url) || trim($url) === '') {
            return [];
        }

        $url = trim($url);
        $path = null;
        if (preg_match('#(custom-ad_slots/\d{4}/\d{2}/\d{2}/[A-Za-z0-9._-]+)#', $url, $m)) {
            $path = $m[1];
        }

        $id = $path !== null
            ? self::encodeId($path)
            : ('ext-'.substr(hash('sha256', $url), 0, 16));
        $name = $path !== null ? basename($path) : 'image';

        return [[
            'id' => $id,
            'hash' => $path !== null ? pathinfo($path, PATHINFO_FILENAME) : $id,
            'original_filename' => $name,
            'name' => $name,
            'mime_type' => 'image/*',
            'size' => 0,
            'size_formatted' => '',
            'download_url' => $url,
            'url' => $url,
            'thumbnail_url' => $url,
            'order' => 0,
            'is_image' => true,
            'path' => $path,
        ]];
    }

    public static function encodeId(string $relativePath): string
    {
        return rtrim(strtr(base64_encode($relativePath), '+/', '-_'), '=');
    }

    public static function decodeId(string $uploadId): ?string
    {
        $padded = strtr($uploadId, '-_', '+/');
        $padLen = (4 - (strlen($padded) % 4)) % 4;
        if ($padLen > 0) {
            $padded .= str_repeat('=', $padLen);
        }
        $decoded = base64_decode($padded, true);
        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        return self::isManagedPath($decoded) ? $decoded : null;
    }

    public static function isManagedPath(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return (bool) preg_match('#^custom-ad_slots/\d{4}/\d{2}/\d{2}/[A-Za-z0-9._-]+$#', $path);
    }

    private function deletePath(string $relative): bool
    {
        $iface = '\\App\\Contracts\\Extension\\StorageInterface';
        if (interface_exists($iface)) {
            try {
                /** @var object $storage */
                $storage = app($iface);
                if (is_object($storage) && method_exists($storage, 'delete')) {
                    try {
                        $storage->delete(self::CATEGORY, $relative);

                        return true;
                    } catch (\Throwable) {
                        // fall through
                    }
                }
                if (is_object($storage) && method_exists($storage, 'deleteFile')) {
                    try {
                        $storage->deleteFile(self::CATEGORY, $relative);

                        return true;
                    } catch (\Throwable) {
                    }
                }
            } catch (\Throwable) {
            }
        }

        try {
            if (Storage::disk('public')->exists($relative)) {
                return (bool) Storage::disk('public')->delete($relative);
            }
        } catch (\Throwable) {
        }

        return false;
    }

    private function putAndUrl(string $relative, string $contents): string
    {
        $iface = '\\App\\Contracts\\Extension\\StorageInterface';
        if (interface_exists($iface)) {
            try {
                /** @var object $storage */
                $storage = app($iface);
                if (is_object($storage) && method_exists($storage, 'put') && method_exists($storage, 'url')) {
                    $ok = (bool) $storage->put(self::CATEGORY, $relative, $contents);
                    if (! $ok) {
                        throw new \RuntimeException('StorageInterface put failed.');
                    }
                    $url = $storage->url(self::CATEGORY, $relative);
                    if (is_string($url) && $url !== '') {
                        return $url;
                    }
                }
            } catch (\Throwable $e) {
                // fall through to public disk
            }
        }

        Storage::disk('public')->put($relative, $contents);
        $url = Storage::disk('public')->url($relative);

        return is_string($url) && $url !== '' ? $url : '/storage/'.$relative;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
