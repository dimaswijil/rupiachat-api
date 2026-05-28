<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_slug', 50);       // 'voice_call', 'video_call', etc.
            $table->string('feature_name', 100);       // 'Voice Call', 'Video Call', etc.
            $table->decimal('price', 15, 2);
            $table->string('status', 20)->default('completed'); // completed, refunded
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
