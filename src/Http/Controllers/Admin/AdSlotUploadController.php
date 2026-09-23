<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdSlots\Services\AdSlotUploadService;

/**
 * Admin image upload for ad URL fields (admin image upload (native JS + legacy FileUploader-compatible)).
 *
 * POST /api/modules/custom-ad_slots/admin/uploads
 * multipart field: file (also accepts image)
 *
 * GET /api/modules/custom-ad_slots/admin/uploads/remembered?field=image_url*
 * Returns last remembered public URL + FileUploader files[] for the admin user.
 *
 * DELETE /api/modules/custom-ad_slots/admin/uploads/{uploadId}
 * FileUploader expects Attachment at response.data.data.
 * Destroy deletes managed storage when id is base64url(path); soft-success otherwise.
 * uploadId=noop with ?field= forgets remember without deleting a blob.
 */
class AdSlotUploadController extends AdminBaseController
{
    public function __construct(
        private AdSlotUploadService $uploadService,
    ) {
        parent::__construct();
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if (! $this->userCanUpload($request)) {
                return $this->error('custom-ad_slots::messages.upload.failed', 403, 'Forbidden');
            }

            $request->validate([
                'file' => ['required_without:image', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
                'image' => ['required_without:file', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            ]);

            $file = $request->file('file') ?: $request->file('image');
            if ($file === null) {
                return $this->error('custom-ad_slots::messages.upload.file_required', 422);
            }

            $payload = $this->uploadService->storeImage($file);

            // Remember URL for this admin field so create/update can persist even if
            // the layout save body races ahead of onUploadComplete (DP temp_key pattern).
            // FileUploader may send uploadParams as FormData `field`, nested keys, or only
            // on the query string (apiEndpoints.upload?field=...); accept all variants.
            $field = self::resolveUploadField($request);
            if ($field !== '' && $request->user()) {
                AdSlotUploadService::rememberUrl($request->user()->id, $field, (string) ($payload['download_url'] ?? $payload['url'] ?? ''));
            }

            // Exact digital_product shape: FileUploader reads Attachment at response.data.data.
            // Use raw json so AdminBaseController::success cannot re-wrap / flatten.
            return response()->json([
                'success' => true,
                'message' => __('custom-ad_slots::messages.upload.success'),
                'data' => [
                    'data' => $payload,
                    'download_url' => $payload['download_url'] ?? null,
                    'url' => $payload['url'] ?? null,
                    'path' => $payload['path'] ?? null,
                    'id' => $payload['id'] ?? null,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.upload.failed', 500, $e->getMessage());
        }
    }

    /**
     * Return the last remembered upload URL for this admin + field (post-upload sync).
     */
    public function remembered(Request $request): JsonResponse
    {
        try {
            if (! $this->userCanUpload($request)) {
                return $this->error('custom-ad_slots::messages.upload.failed', 403, 'Forbidden');
            }

            $field = self::resolveUploadField($request);
            if (! AdSlotUploadService::isUrlField($field)) {
                return $this->error('custom-ad_slots::messages.upload.failed', 422, 'Invalid field');
            }

            $userId = (int) ($request->user()?->id ?? 0);
            $url = AdSlotUploadService::peekRememberedUrl($userId, $field);
            $files = AdSlotUploadService::uploaderFilesFromUrl($url);

            return $this->success('custom-ad_slots::messages.upload.success', [
                'field' => $field,
                'url' => $url,
                'files' => $files,
                'file' => $files[0] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.upload.failed', 500, $e->getMessage());
        }
    }

    /**
     * Delete stored file when identifiable; always 2xx so FileUploader unblocks.
     * Client clears image_url* on onRemove / empty onFilesChange.
     */
    public function destroy(Request $request, int|string $uploadId): JsonResponse
    {
        try {
            if (! $this->userCanUpload($request)) {
                return $this->error('custom-ad_slots::messages.upload.failed', 403, 'Forbidden');
            }

            $result = $this->uploadService->deleteByUploadId($uploadId);

            $field = self::resolveUploadField($request);
            if ($field !== '' && $request->user()) {
                AdSlotUploadService::forgetUrl($request->user()->id, $field);
            }

            return $this->success('custom-ad_slots::messages.upload.delete_success', [
                'data' => true,
                'id' => $result['id'],
                'deleted' => $result['deleted'],
                'path' => $result['path'],
            ]);
        } catch (\Exception $e) {
            $field = self::resolveUploadField($request);
            if ($field !== '' && $request->user()) {
                AdSlotUploadService::forgetUrl($request->user()->id, $field);
            }
            // Soft-success: still let the uploader UI clear the chip.
            return $this->success('custom-ad_slots::messages.upload.delete_success', [
                'data' => true,
                'id' => (string) $uploadId,
                'deleted' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Resolve FileUploader field name from query, FormData, or nested uploadParams.
     */
    private static function resolveUploadField(Request $request): string
    {
        $candidates = [
            $request->query('field'),
            $request->input('field'),
            $request->input('uploadParams.field'),
            $request->input('params.field'),
            $request->input('upload_params.field'),
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function userCanUpload(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        foreach (['custom-ad_slots.ads.create', 'custom-ad_slots.ads.update'] as $perm) {
            try {
                if (method_exists($user, 'can') && $user->can($perm)) {
                    return true;
                }
            } catch (\Throwable) {
            }
            try {
                if (class_exists('\App\Helpers\PermissionHelper')
                    && method_exists('\App\Helpers\PermissionHelper', 'check')
                    && \App\Helpers\PermissionHelper::check($perm)) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        if (method_exists($user, 'hasRole')) {
            try {
                return (bool) ($user->hasRole('admin') || $user->hasRole('manager'));
            } catch (\Throwable) {
            }
        }

        return false;
    }
}
