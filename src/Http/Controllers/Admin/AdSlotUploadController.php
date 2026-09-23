<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdSlots\Services\AdSlotUploadService;

/**
 * Admin image upload for ad URL fields (maker_bids-style upload_token + FileUploader).
 *
 * POST /api/modules/custom-ad_slots/admin/uploads
 * multipart: file|image + field + upload_token|token
 * Stages public URL under (user, token, field) for claim on ad store/update.
 *
 * GET /api/modules/custom-ad_slots/admin/form-defaults
 * Returns fresh upload_token for the admin form.
 *
 * DELETE /api/modules/custom-ad_slots/admin/uploads/{uploadId}
 * Soft-success destroy; uploadId=noop?field= forgets staged URL for token+field.
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
            $token = self::resolveUploadToken($request);
            if ($field !== '' && $request->user()) {
                AdSlotUploadService::rememberUrl(
                    $request->user()->id,
                    $field,
                    (string) ($payload['download_url'] ?? $payload['url'] ?? ''),
                    $token
                );
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
            $token = self::resolveUploadToken($request);
            $url = AdSlotUploadService::peekRememberedUrl($userId, $field, $token);
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
            $token = self::resolveUploadToken($request);
            if ($field !== '' && $request->user()) {
                AdSlotUploadService::forgetUrl($request->user()->id, $field, $token);
            }

            return $this->success('custom-ad_slots::messages.upload.delete_success', [
                'data' => true,
                'id' => $result['id'],
                'deleted' => $result['deleted'],
                'path' => $result['path'],
            ]);
        } catch (\Exception $e) {
            $field = self::resolveUploadField($request);
            $token = self::resolveUploadToken($request);
            if ($field !== '' && $request->user()) {
                AdSlotUploadService::forgetUrl($request->user()->id, $field, $token);
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
     * Fresh upload_token for admin create/edit form (maker_bids jobs/form-defaults).
     */
    public function formDefaults(Request $request): JsonResponse
    {
        try {
            if (! $this->userCanUpload($request)) {
                return $this->error('custom-ad_slots::messages.upload.failed', 403, 'Forbidden');
            }

            return $this->success('custom-ad_slots::messages.upload.success', [
                'upload_token' => AdSlotUploadService::newUploadToken(),
            ]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.upload.failed', 500, $e->getMessage());
        }
    }

    /**
     * Resolve maker_bids-style upload_token from query/body/uploadParams.
     */
    private static function resolveUploadToken(Request $request): ?string
    {
        $candidates = [
            $request->query('upload_token'),
            $request->query('token'),
            $request->input('upload_token'),
            $request->input('token'),
            $request->input('uploadParams.upload_token'),
            $request->input('uploadParams.token'),
            $request->input('params.upload_token'),
            $request->input('params.token'),
            $request->input('upload_params.upload_token'),
            $request->input('upload_params.token'),
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
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
