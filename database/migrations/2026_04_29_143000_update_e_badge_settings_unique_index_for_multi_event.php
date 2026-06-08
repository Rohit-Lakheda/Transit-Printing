<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('e_badge_settings', function (Blueprint $table) {
            // Drop old unique(name) if present, then add unique(event_id, name)
            try {
                $table->dropUnique('e_badge_settings_name_unique');
            } catch (\Throwable $e) {
                // Index may already be missing on some databases.
            }

            $table->unique(['event_id', 'name'], 'e_badge_settings_event_id_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('e_badge_settings', function (Blueprint $table) {
            try {
                $table->dropUnique('e_badge_settings_event_id_name_unique');
            } catch (\Throwable $e) {
                // Ignore if missing.
            }

            $table->unique('name', 'e_badge_settings_name_unique');
        });
    }
};
