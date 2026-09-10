<?php

namespace Modules\Custom\AdSlots;

use App\Extension\AbstractModule;

/**
 * 광고 슬롯 모듈
 *
 * home/shop 영역별 정적·동적 광고 아이템 CRUD 및 공개 placements API 제공.
 */
class Module extends AbstractModule
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRoles(): array
    {
        return [];
    }

    /**
     * 계층형 권한 정의 (hello_module 패턴).
     *
     * 최종 권한 식별자 예:
     * - custom-ad_slots.ads.read
     * - custom-ad_slots.ads.create
     * - custom-ad_slots.ads.update
     * - custom-ad_slots.ads.delete
     *
     * @return array<string, mixed>
     */
    public function getPermissions(): array
    {
        return [
            'name' => [
                'ko' => '광고 슬롯',
                'en' => 'Ad Slots',
            ],
            'description' => [
                'ko' => '광고 슬롯 모듈 권한',
                'en' => 'Ad slots module permissions',
            ],
            'categories' => [
                [
                    'identifier' => 'ads',
                    'resource_route_key' => 'ad',
                    'owner_key' => null,
                    'name' => [
                        'ko' => '광고 관리',
                        'en' => 'Ad Management',
                    ],
                    'description' => [
                        'ko' => '광고 슬롯 아이템 관리 권한',
                        'en' => 'Manage ad slot items',
                    ],
                    'permissions' => [
                        [
                            'action' => 'read',
                            'name' => [
                                'ko' => '광고 조회',
                                'en' => 'View Ads',
                            ],
                            'description' => [
                                'ko' => '광고 목록 및 상세 조회',
                                'en' => 'View ad list and details',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin', 'manager'],
                        ],
                        [
                            'action' => 'create',
                            'name' => [
                                'ko' => '광고 생성',
                                'en' => 'Create Ad',
                            ],
                            'description' => [
                                'ko' => '새 광고 아이템 생성',
                                'en' => 'Create new ad item',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin', 'manager'],
                        ],
                        [
                            'action' => 'update',
                            'name' => [
                                'ko' => '광고 수정',
                                'en' => 'Update Ad',
                            ],
                            'description' => [
                                'ko' => '광고 아이템 수정 및 활성 토글',
                                'en' => 'Update ad item and toggle active',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin', 'manager'],
                        ],
                        [
                            'action' => 'delete',
                            'name' => [
                                'ko' => '광고 삭제',
                                'en' => 'Delete Ad',
                            ],
                            'description' => [
                                'ko' => '광고 아이템 삭제',
                                'en' => 'Delete ad item',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAdminMenus(): array
    {
        return [
            [
                'name' => [
                    'ko' => '광고 슬롯',
                    'en' => 'Ad Slots',
                ],
                'slug' => 'custom-ad_slots',
                'url' => '/admin/ad-slots',
                'icon' => 'fas fa-ad',
                'order' => 80,
                'permission' => 'custom-ad_slots.ads.read',
            ],
        ];
    }

    /**
     * Event Hook listeners (official theme ad placements).
     *
     * @return array<int, class-string>
     */
    public function getHookListeners(): array
    {
        return [
            \Modules\Custom\AdSlots\Listeners\AdPlacementLayoutListener::class,
        ];
    }
}
