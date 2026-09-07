<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ad_slots_items', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('광고 아이템 고유번호');
            $table->string('slot_key', 64)->comment('슬롯 키 (home.top 등)');
            $table->string('type', 16)->comment('static|dynamic');
            $table->string('title', 255)->nullable()->comment('제목');
            $table->string('image_url', 2048)->nullable()->comment('이미지 URL');
            $table->string('link_url', 2048)->nullable()->comment('링크 URL');
            $table->text('html_content')->nullable()->comment('동적 HTML (XSS 주의)');
            $table->string('script_src', 2048)->nullable()->comment('외부 스크립트 URL');
            $table->integer('sort_order')->default(0)->comment('정렬 순서');
            $table->boolean('is_active')->default(true)->comment('활성 여부');
            $table->timestamp('starts_at')->nullable()->comment('게시 시작');
            $table->timestamp('ends_at')->nullable()->comment('게시 종료');
            $table->timestamps();

            $table->index('slot_key', 'ad_slots_items_slot_key_index');
            $table->index(['is_active', 'sort_order'], 'ad_slots_items_active_sort_index');
            $table->index(['starts_at', 'ends_at'], 'ad_slots_items_schedule_index');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('ad_slots_items', function (Blueprint $table) {
                $table->comment('광고 슬롯 아이템');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_slots_items');
    }
};
