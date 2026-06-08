<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_badge_layout_settings', function (Blueprint $table) {
            $table->id();
            $table->string('Category');
            $table->string('field_name');
            $table->string('static_text_key')->nullable();
            $table->text('static_text_value')->nullable();
            $table->decimal('margin_top', 8, 2)->default(0);
            $table->integer('sequence')->default(0);
            $table->string('text_align')->default('left');
            $table->string('font_family')->default('Tw Cen MT');
            $table->string('font_weight')->default('normal');
            $table->string('color')->default('#000000');
            $table->decimal('font_size', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['Category', 'sequence']);
            $table->index('field_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_badge_layout_settings');
    }
};
