<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_details', 'client_registration_id')) {
            Schema::table('user_details', function (Blueprint $table) {
                $table->string('client_registration_id', 64)->nullable()->after('id');
                $table->unique(['event_id', 'client_registration_id'], 'user_details_event_client_reg_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_details', 'client_registration_id')) {
            Schema::table('user_details', function (Blueprint $table) {
                try {
                    $table->dropUnique('user_details_event_client_reg_unique');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('client_registration_id');
            });
        }
    }
};
