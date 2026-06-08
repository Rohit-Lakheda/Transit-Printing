<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_badge_mail_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_detail_id')->nullable();
            $table->string('regid')->nullable();
            $table->string('category')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('success');
            $table->text('message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index('regid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_badge_mail_logs');
    }
};
