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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $defaultEventId = DB::table('events')->insertGetId([
            'name' => 'Default Event',
            'description' => 'Auto-created default event for existing records.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tables = [
            'categories',
            'badge_display_settings',
            'badge_layout_settings',
            'user_details',
            'locations',
            'location_categories',
            'blocked_regids',
            'blocked_regid_locations',
            'master_badges',
            'master_badge_locations',
            'bypassed_regids',
            'bypassed_regid_locations',
            'bypassed_regid_usage_logs',
            'printing_logs',
            'scanning_logs',
            'api_configurations',
            'get_data_api_configurations',
            'event_settings',
            'lead_settings',
            'mail_configurations',
            'user_credentials',
            'user_device_logins',
            'lead_scans',
            'lead_scan_attempt_logs',
            'lead_password_resets',
            'e_badge_settings',
            'e_badge_layout_settings',
            'e_badge_mail_logs',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'event_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $tableBlueprint) {
                $tableBlueprint->unsignedBigInteger('event_id')->nullable()->index();
            });

            DB::table($table)->update(['event_id' => $defaultEventId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'categories',
            'badge_display_settings',
            'badge_layout_settings',
            'user_details',
            'locations',
            'location_categories',
            'blocked_regids',
            'blocked_regid_locations',
            'master_badges',
            'master_badge_locations',
            'bypassed_regids',
            'bypassed_regid_locations',
            'bypassed_regid_usage_logs',
            'printing_logs',
            'scanning_logs',
            'api_configurations',
            'get_data_api_configurations',
            'event_settings',
            'lead_settings',
            'mail_configurations',
            'user_credentials',
            'user_device_logins',
            'lead_scans',
            'lead_scan_attempt_logs',
            'lead_password_resets',
            'e_badge_settings',
            'e_badge_layout_settings',
            'e_badge_mail_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'event_id')) {
                Schema::table($table, function (Blueprint $tableBlueprint) {
                    $tableBlueprint->dropColumn('event_id');
                });
            }
        }

        Schema::dropIfExists('events');
    }
};
