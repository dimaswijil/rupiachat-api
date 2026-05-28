<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_slug', 50);
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'feature_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_features');
    }
};
