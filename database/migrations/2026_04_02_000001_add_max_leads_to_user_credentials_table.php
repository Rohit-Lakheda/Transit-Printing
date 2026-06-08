<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_credentials', function (Blueprint $table) {
            $table->unsignedInteger('max_leads')->nullable()->after('max_devices');
        });
    }

    public function down(): void
    {
        Schema::table('user_credentials', function (Blueprint $table) {
            $table->dropColumn('max_leads');
        });
    }
};
