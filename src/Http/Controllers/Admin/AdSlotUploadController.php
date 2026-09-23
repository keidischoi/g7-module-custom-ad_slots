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
 * Layout delete URL uses literal /uploads/noop (no :id) so route.id is never stolen.
 * Soft-success no-op: ad slot images are URL-field sourced; clearing the
 * form URL on the client is enough. FileUploader still expects delete to 2xx.
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
            $user = $request->user();
            $allowed = false;
            if ($user) {
                foreach (['custom-ad_slots.ads.create', 'custom-ad_slots.ads.update'] as $perm) {
                    try {
                        if (method_exists($user, 'can') && $user->can($perm)) {
                            $allowed = true;
                            break;
                        }
                    } catch (\Throwable) {
                    }
                    // G7 permission helper fallbacks
                    try {
                        if (class_exists('\App\Helpers\PermissionHelper')
                            && method_exists('\App\Helpers\PermissionHelper', 'check')
                            && \App\Helpers\PermissionHelper::check($perm)) {
                            $allowed = true;
                            break;
                        }
                    } catch (\Throwable) {
                    }
                }
                // Admin role fallback
                if (! $allowed && method_exists($user, 'hasRole')) {
                    try {
                        $allowed = (bool) ($user->hasRole('admin') || $user->hasRole('manager'));
                    } catch (\Throwable) {
                    }
                }
            }
            if (! $allowed) {
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

            return $this->success(
                'custom-ad_slots::messages.upload.success',
                $payload,
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.upload.failed', 500, $e->getMessage());
        }
    }

    /**
     * Soft-delete for FileUploader. Ad images are referenced by URL fields on the
     * ad item; the form clears image_url* locally on remove. Always succeed so the
     * uploader UI unblocks without reintroducing files/value binding hangs.
     */
    public function destroy(Request $request, int|string $uploadId): JsonResponse
    {
        try {
            $user = $request->user();
            $allowed = false;
            if ($user) {
                foreach (['custom-ad_slots.ads.create', 'custom-ad_slots.ads.update'] as $perm) {
                    try {
                        if (method_exists($user, 'can') && $user->can($perm)) {
                            $allowed = true;
                            break;
                        }
                    } catch (\Throwable) {
                    }
                    try {
                        if (class_exists('\App\Helpers\PermissionHelper')
                            && method_exists('\App\Helpers\PermissionHelper', 'check')
                            && \App\Helpers\PermissionHelper::check($perm)) {
                            $allowed = true;
                            break;
                        }
                    } catch (\Throwable) {
                    }
                }
                if (! $allowed && method_exists($user, 'hasRole')) {
                    try {
                        $allowed = (bool) ($user->hasRole('admin') || $user->hasRole('manager'));
                    } catch (\Throwable) {
                    }
                }
            }
            if (! $allowed) {
                return $this->error('custom-ad_slots::messages.upload.failed', 403, 'Forbidden');
            }

            return $this->success('custom-ad_slots::messages.upload.delete_success', [
                'id' => $uploadId,
                'deleted' => true,
            ]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.upload.failed', 500, $e->getMessage());
        }
    }
}
