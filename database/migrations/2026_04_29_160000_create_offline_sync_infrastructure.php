<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('device_id', 120)->index();
            $table->string('device_name')->nullable();
            $table->string('device_type', 50)->default('scanner');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 30)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'device_id']);
        });

        if (!Schema::hasColumn('scanning_logs', 'client_scan_id')) {
            Schema::table('scanning_logs', function (Blueprint $table) {
                $table->string('client_scan_id', 64)->nullable()->after('id');
                $table->string('device_id', 120)->nullable()->after('client_scan_id');
                $table->string('source', 30)->default('online')->after('device_id');
                $table->unique(['event_id', 'client_scan_id'], 'scanning_logs_event_client_scan_unique');
            });
        }

        if (!Schema::hasColumn('printing_logs', 'client_print_id')) {
            Schema::table('printing_logs', function (Blueprint $table) {
                $table->string('client_print_id', 64)->nullable()->after('id');
                $table->string('device_id', 120)->nullable()->after('client_print_id');
                $table->string('source', 30)->default('online')->after('device_id');
                $table->unique(['event_id', 'client_print_id'], 'printing_logs_event_client_print_unique');
            });
        }

        Schema::create('sync_dead_letter_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->index();
            $table->string('device_id', 120)->nullable()->index();
            $table->string('entity_type', 40);
            $table->json('payload');
            $table->text('error_message');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('failed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_dead_letter_logs');
        Schema::dropIfExists('sync_devices');

        if (Schema::hasColumn('scanning_logs', 'client_scan_id')) {
            Schema::table('scanning_logs', function (Blueprint $table) {
                try {
                    $table->dropUnique('scanning_logs_event_client_scan_unique');
                } catch (\Throwable $e) {
                }
                $table->dropColumn(['client_scan_id', 'device_id', 'source']);
            });
        }

        if (Schema::hasColumn('printing_logs', 'client_print_id')) {
            Schema::table('printing_logs', function (Blueprint $table) {
                try {
                    $table->dropUnique('printing_logs_event_client_print_unique');
                } catch (\Throwable $e) {
                }
                $table->dropColumn(['client_print_id', 'device_id', 'source']);
            });
        }
    }
};
