<?php

namespace Modules\Custom\AdSlots\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Store / delete ad images for URL fields (desktop/mobile/legacy).
 *
 * Prefers G7 StorageInterface (images category); falls back to public disk.
 * File ids are base64url(relative path) so destroy can remove the blob.
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
