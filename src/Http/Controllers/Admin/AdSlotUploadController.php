<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdSlots\Services\AdSlotUploadService;

/**
 * Admin image upload for ad URL fields (FileUploader-compatible).
 *
 * POST /api/modules/custom-ad_slots/admin/uploads
 * multipart field: file (also accepts image)
 *
 * DELETE /api/modules/custom-ad_slots/admin/uploads/{uploadId}
 * FileUploader expects Attachment at response.data.data.
 * Destroy deletes managed storage when id is base64url(path); soft-success otherwise.
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

            // FileUploader reads Attachment from response.data.data (digital_product contract).
            return $this->success(
                'custom-ad_slots::messages.upload.success',
                array_merge($payload, [
                    'data' => $payload,
                ]),
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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

            return $this->success('custom-ad_slots::messages.upload.delete_success', [
                'data' => true,
                'id' => $result['id'],
                'deleted' => $result['deleted'],
                'path' => $result['path'],
            ]);
        } catch (\Exception $e) {
            // Soft-success: still let the uploader UI clear the chip.
            return $this->success('custom-ad_slots::messages.upload.delete_success', [
                'data' => true,
                'id' => (string) $uploadId,
                'deleted' => false,
                'error' => $e->getMessage(),
            ]);
        }
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
