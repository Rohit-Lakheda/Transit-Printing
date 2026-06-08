<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that received event_id for multi-event mode.
     *
     * @var array<int, string>
     */
    protected array $tables = [
        'api_configurations',
        'badge_display_settings',
        'badge_layout_settings',
        'blocked_regids',
        'bypassed_regids',
        'bypassed_regid_usage_logs',
        'categories',
        'e_badge_layout_settings',
        'e_badge_mail_logs',
        'e_badge_settings',
        'event_settings',
        'get_data_api_configurations',
        'lead_password_resets',
        'lead_scan_attempt_logs',
        'lead_scans',
        'lead_settings',
        'location_categories',
        'locations',
        'mail_configurations',
        'master_badges',
        'printing_logs',
        'scanning_logs',
        'user_credentials',
        'user_details',
        'user_device_logins',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'event_id')) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $blueprint) {
                    try {
                        $blueprint->dropForeign(['event_id']);
                    } catch (\Throwable $e) {
                    }
                    $blueprint->dropColumn('event_id');
                });
            } catch (\Throwable $e) {
                // Keep migration resilient for environments with custom indexes/constraints.
            }
        }

        if (Schema::hasTable('events')) {
            Schema::drop('events');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'event_id')) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedBigInteger('event_id')->nullable()->index();
                });
            } catch (\Throwable $e) {
            }
        }
    }
};

