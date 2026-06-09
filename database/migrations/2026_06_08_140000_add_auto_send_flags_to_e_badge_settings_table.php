<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_badge_settings', function (Blueprint $table) {
            $table->boolean('auto_send_email_on_api_registration')->default(true)->after('whatsapp_configuration_id');
            $table->boolean('auto_send_whatsapp_on_api_registration')->default(true)->after('auto_send_email_on_api_registration');
        });
    }

    public function down(): void
    {
        Schema::table('e_badge_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_send_email_on_api_registration',
                'auto_send_whatsapp_on_api_registration',
            ]);
        });
    }
};
