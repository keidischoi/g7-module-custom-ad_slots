<?php

namespace Modules\Custom\AdSlots\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 마일리지 적립 원장 행
 *
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property string $target_key
 * @property int $amount
 * @property int|null $transaction_id
 * @property \Illuminate\Support\Carbon|null $reclaimed_at
 * @property int $reclaimed_amount
 */
class AdSlotsMileageReward extends Model
{
    protected $table = 'ad_slots_mileage_rewards';

    protected $fillable = [
        'user_id',
        'action',
        'target_key',
        'amount',
        'transaction_id',
        'reclaimed_at',
        'reclaimed_amount',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'amount' => 'integer',
            'transaction_id' => 'integer',
            'reclaimed_at' => 'datetime',
            'reclaimed_amount' => 'integer',
        ];
    }
}
