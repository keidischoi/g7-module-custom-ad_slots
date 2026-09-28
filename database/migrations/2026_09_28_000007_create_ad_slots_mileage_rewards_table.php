<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 마일리지 적립 원장 (배너 클릭 적립 등). 같은 대상은 1번만 적립.
     */
    public function up(): void
    {
        if (Schema::hasTable('ad_slots_mileage_rewards')) {
            return;
        }

        Schema::create('ad_slots_mileage_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('적립 회원 ID');
            $table->string('action', 40)->comment('적립 규칙 (click_reward 등)');
            $table->string('target_key', 100)->comment('적립 대상 키 (item:{id}:{Y-m-d})');
            $table->integer('amount')->default(0)->comment('적립액');
            $table->unsignedBigInteger('transaction_id')->nullable()->comment('이커머스 마일리지 거래 ID');
            $table->timestamp('reclaimed_at')->nullable()->comment('회수 시각');
            $table->integer('reclaimed_amount')->default(0)->comment('회수액');
            $table->timestamps();

            $table->unique(['user_id', 'action', 'target_key'], 'ad_slots_mr_user_action_target_unique');
            $table->index(['user_id', 'action', 'created_at'], 'ad_slots_mr_user_action_created_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('ad_slots_mileage_rewards', function (Blueprint $table) {
                $table->comment('광고 슬롯 마일리지 적립 원장');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_slots_mileage_rewards');
    }
};
