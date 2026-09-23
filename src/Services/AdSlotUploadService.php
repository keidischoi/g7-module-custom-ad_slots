<?php

namespace Modules\Custom\AdSlots\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

/**
 * Store ad images for URL fields (desktop/mobile/legacy).
 *
 * Prefers G7 StorageInterface (images category); falls back to public disk.
 */
class AdSlotUploadService
{
    private const CATEGORY = 'images';

    private const DIR = 'custom-ad_slots';

    /**
     * @return array{
     *     id: int,
     *     hash: string,
     *     original_filename: string,
     *     mime_type: string,
     *     size: int,
     *     size_formatted: string,
     *     download_url: string,
     *     url: string,
     *     order: int,
     *     is_image: bool,
     *     path: string
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

        return [
            'id' => (int) hexdec(substr(md5($hash), 0, 7)),
            'hash' => $hash,
            'original_filename' => (string) $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'download_url' => $url,
            'url' => $url,
            'order' => 0,
            'is_image' => true,
            'path' => $relative,
        ];
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
