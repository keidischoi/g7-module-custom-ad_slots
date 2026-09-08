<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-ad flag: open link in new tab (default true matches prior _blank behavior).
     */
    public function up(): void
    {
        Schema::table('ad_slots_items', function (Blueprint $table) {
            $table->boolean('open_in_new_tab')
                ->default(true)
                ->after('prevent_right_click')
                ->comment('스토어프론트 광고 링크를 새 창/탭에서 열기');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_slots_items', function (Blueprint $table) {
            $table->dropColumn('open_in_new_tab');
        });
    }
};
