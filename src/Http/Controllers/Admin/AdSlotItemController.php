<?php

namespace Modules\Custom\AdSlots\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Custom\AdSlots\Http\Requests\Admin\StoreAdSlotItemRequest;
use Modules\Custom\AdSlots\Http\Requests\Admin\UpdateAdSlotItemRequest;
use Modules\Custom\AdSlots\Http\Resources\AdSlotItemResource;
use Modules\Custom\AdSlots\Models\AdSlotItem;
use Modules\Custom\AdSlots\Services\AdSlotService;
use Modules\Custom\AdSlots\Services\AdSlotUploadService;

/**
 * 관리자 광고 슬롯 CRUD + toggle
 *
 * 미들웨어는 routes/api.php 에서 permission:admin,custom-ad_slots.ads.* 스타일로 지정
 * (G7 hello_module 과 동일 패턴).
 */
class AdSlotItemController extends AdminBaseController
{
    public function __construct(
        private AdSlotService $adSlotService,
    ) {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $paginator = $this->adSlotService->paginateAdmin($request->only([
                'slot_key',
                'type',
                'is_active',
                'per_page',
            ]));

            return $this->success('custom-ad_slots::messages.ad.fetch_success', [
                'data' => AdSlotItemResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'slot_keys' => AdSlotItem::SLOT_KEYS,
            ]);
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.fetch_failed', 500, $e->getMessage());
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $item = $this->adSlotService->findOrFail($id);

            try {
                $payload = (new AdSlotItemResource($item))->resolve();
            } catch (\Throwable $e) {
                // Soft-fail resource mapping (e.g. missing size/placements columns)
                // so the edit form can still hydrate core fields.
                $payload = [
                    'id' => $item->id,
                    'slot_key' => $item->slot_key,
                    'type' => $item->type,
                    'title' => $item->title,
                    'image_url' => $item->image_url,
                    'image_url_desktop' => $item->image_url_desktop,
                    'image_url_mobile' => $item->image_url_mobile,
                    'uploader_image_url' => AdSlotUploadService::uploaderFilesFromUrl($item->image_url),
                    'uploader_image_url_desktop' => AdSlotUploadService::uploaderFilesFromUrl($item->image_url_desktop),
                    'uploader_image_url_mobile' => AdSlotUploadService::uploaderFilesFromUrl($item->image_url_mobile),
                    'bg_color' => $item->bg_color,
                    'link_url' => $item->link_url,
                    'html_content' => $item->html_content,
                    'script_src' => $item->script_src,
                    'sort_order' => $item->sort_order,
                    'is_active' => (bool) $item->is_active,
                    'prevent_right_click' => (bool) $item->prevent_right_click,
                    'open_in_new_tab' => (bool) ($item->open_in_new_tab ?? true),
                    'size_mode' => null,
                    'aspect_desktop' => null,
                    'aspect_mobile' => null,
                    'width_px' => null,
                    'height_px' => null,
                    'max_width_px' => null,
                    'starts_at' => optional($item->starts_at)?->toIso8601String(),
                    'ends_at' => optional($item->ends_at)?->toIso8601String(),
                    'starts_at_local' => null,
                    'ends_at_local' => null,
                    '_resource_warning' => $e->getMessage(),
                ];
            }

            // initLocal:"form" expects the item at response.data (not nested under data.data).
            return $this->success(
                'custom-ad_slots::messages.ad.fetch_success',
                $payload
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.ad.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.fetch_failed', 500, $e->getMessage());
        }
    }

    public function store(StoreAdSlotItemRequest $request): JsonResponse
    {
        try {
            $item = $this->adSlotService->create($request->validated());

            return $this->success(
                'custom-ad_slots::messages.ad.create_success',
                (new AdSlotItemResource($item))->resolve(),
                201
            );
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.create_failed', 500, $e->getMessage());
        }
    }

    public function update(UpdateAdSlotItemRequest $request, int $id): JsonResponse
    {
        try {
            $item = $this->adSlotService->findOrFail($id);
            $item = $this->adSlotService->update($item, $request->validated());

            return $this->success(
                'custom-ad_slots::messages.ad.update_success',
                (new AdSlotItemResource($item))->resolve()
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.ad.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.update_failed', 500, $e->getMessage());
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $item = $this->adSlotService->findOrFail($id);
            $this->adSlotService->delete($item);

            return $this->success('custom-ad_slots::messages.ad.delete_success');
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.ad.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.delete_failed', 500, $e->getMessage());
        }
    }

    /**
     * is_active 토글
     */
    public function toggle(int $id): JsonResponse
    {
        try {
            $item = $this->adSlotService->findOrFail($id);
            $item = $this->adSlotService->toggle($item);

            return $this->success(
                'custom-ad_slots::messages.ad.toggle_success',
                (new AdSlotItemResource($item))->resolve()
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.ad.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.toggle_failed', 500, $e->getMessage());
        }
    }

    /**
     * 광고 복제
     */
    public function duplicate(int $id): JsonResponse
    {
        try {
            $item = $this->adSlotService->findOrFail($id);
            $copy = $this->adSlotService->duplicate($item);

            return $this->success(
                'custom-ad_slots::messages.ad.duplicate_success',
                (new AdSlotItemResource($copy))->resolve(),
                201
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('custom-ad_slots::messages.ad.not_found');
        } catch (\Exception $e) {
            return $this->error('custom-ad_slots::messages.ad.duplicate_failed', 500, $e->getMessage());
        }
    }
}
