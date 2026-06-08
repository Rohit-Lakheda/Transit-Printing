<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_badge_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('default')->unique();
            $table->string('email_subject')->nullable();
            $table->longText('email_body')->nullable();
            $table->unsignedBigInteger('mail_configuration_id')->nullable();
            $table->timestamps();
        });

        DB::table('e_badge_settings')->insert([
            'name' => 'default',
            'email_subject' => 'Your E-Badge for {{Category}}',
            'email_body' => '<p>Dear {{Name}},</p><p>Please find your e-badge attached.</p><p>Category: {{Category}}<br>RegID: {{RegID}}</p><p>Regards,<br>Event Team</p>',
            'mail_configuration_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('e_badge_settings');
    }
};
