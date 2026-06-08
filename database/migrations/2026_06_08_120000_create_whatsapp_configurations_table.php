<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Default');
            $table->string('provider'); // aisensy, interakt
            $table->text('api_key')->nullable();
            $table->string('api_url')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('template_name')->nullable();
            $table->string('language_code')->nullable()->default('en');
            $table->string('source')->nullable();
            $table->string('callback_data')->nullable();
            $table->string('default_country_code')->default('+91');
            $table->text('template_params')->nullable();
            $table->text('header_params')->nullable();
            $table->text('body_params')->nullable();
            $table->string('media_url_param')->nullable();
            $table->string('media_filename')->nullable();
            $table->boolean('include_media')->default(true);
            $table->text('tags')->nullable();
            $table->json('attributes')->nullable();
            $table->boolean('ssl_verify')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('e_badge_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('whatsapp_configuration_id')->nullable()->after('mail_configuration_id');
        });
    }

    public function down(): void
    {
        Schema::table('e_badge_settings', function (Blueprint $table) {
            $table->dropColumn('whatsapp_configuration_id');
        });

        Schema::dropIfExists('whatsapp_configurations');
    }
};
