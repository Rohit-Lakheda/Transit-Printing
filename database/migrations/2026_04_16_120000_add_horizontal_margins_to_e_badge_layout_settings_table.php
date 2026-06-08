<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_badge_layout_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('e_badge_layout_settings', 'margin_left')) {
                $table->decimal('margin_left', 8, 2)->default(0)->after('margin_top');
            }

            if (!Schema::hasColumn('e_badge_layout_settings', 'margin_right')) {
                $table->decimal('margin_right', 8, 2)->default(0)->after('margin_left');
            }
        });
    }

    public function down(): void
    {
        Schema::table('e_badge_layout_settings', function (Blueprint $table) {
            if (Schema::hasColumn('e_badge_layout_settings', 'margin_right')) {
                $table->dropColumn('margin_right');
            }

            if (Schema::hasColumn('e_badge_layout_settings', 'margin_left')) {
                $table->dropColumn('margin_left');
            }
        });
    }
};
