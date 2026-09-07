<?php

namespace Modules\Custom\AdSlots\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Custom\AdSlots\Models\AdSlotItem;

/**
 * 광고 슬롯 비즈니스 로직
 */
class AdSlotService
{
    /**
     * 공개 placements: slot 지정 시 해당 슬롯 목록, 없으면 슬롯별 그룹.
     *
     * @return Collection<int, AdSlotItem>|SupportCollection<string, Collection<int, AdSlotItem>>
     */
    public function getPublicPlacements(?string $slotKey = null): Collection|SupportCollection
    {
        $query = AdSlotItem::query()
            ->currentlyActive()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($slotKey !== null && $slotKey !== '') {
            return $query->where('slot_key', $slotKey)->get();
        }

        return $query->get()->groupBy('slot_key');
    }

    /**
     * 관리자 목록 (필터/페이지네이션).
     *
     * @param  array{slot_key?: string, type?: string, is_active?: bool|string, per_page?: int}  $filters
     */
    public function paginateAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = AdSlotItem::query()->orderBy('slot_key')->orderBy('sort_order')->orderBy('id');

        if (! empty($filters['slot_key'])) {
            $query->where('slot_key', $filters['slot_key']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 100));

        return $query->paginate($perPage);
    }

    public function findOrFail(int $id): AdSlotItem
    {
        return AdSlotItem::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AdSlotItem
    {
        return AdSlotItem::query()->create($this->normalize($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AdSlotItem $item, array $data): AdSlotItem
    {
        $item->fill($this->normalize($data));
        $item->save();

        return $item->refresh();
    }

    public function delete(AdSlotItem $item): bool
    {
        return (bool) $item->delete();
    }


    /**
     * 기존 광고를 복제 (제목에 " (복제)" 붙이고 비활성으로 생성).
     */
    public function duplicate(AdSlotItem $item): AdSlotItem
    {
        $title = $item->title;
        if ($title !== null && $title !== '') {
            $title = $title.' (복제)';
        } else {
            $title = ($item->slot_key ?? 'ad').' (복제)';
        }

        return $this->create([
            'slot_key' => $item->slot_key,
            'type' => $item->type,
            'title' => $title,
            'image_url' => $item->image_url,
            'image_url_desktop' => $item->image_url_desktop,
            'image_url_mobile' => $item->image_url_mobile,
            'bg_color' => $item->bg_color,
            'link_url' => $item->link_url,
            'html_content' => $item->html_content,
            'script_src' => $item->script_src,
            'sort_order' => (int) $item->sort_order,
            'is_active' => false,
            'starts_at' => optional($item->starts_at)?->toIso8601String(),
            'ends_at' => optional($item->ends_at)?->toIso8601String(),
        ]);
    }

    public function toggle(AdSlotItem $item): AdSlotItem
    {
        $item->is_active = ! $item->is_active;
        $item->save();

        return $item->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $nullableStrings = [
            'title',
            'image_url',
            'image_url_desktop',
            'image_url_mobile',
            'bg_color',
            'link_url',
            'html_content',
            'script_src',
            'starts_at',
            'ends_at',
        ];
        foreach ($nullableStrings as $key) {
            if (array_key_exists($key, $data) && ($data[$key] === '' || $data[$key] === null)) {
                $data[$key] = null;
            }
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== '') {
            $data['sort_order'] = (int) $data['sort_order'];
        } elseif (array_key_exists('sort_order', $data) && ($data['sort_order'] === '' || $data['sort_order'] === null)) {
            $data['sort_order'] = 0;
        }

        if (array_key_exists('is_active', $data)) {
            if ($data['is_active'] === '' || $data['is_active'] === null) {
                $data['is_active'] = true;
            } else {
                $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $data;
    }
}
