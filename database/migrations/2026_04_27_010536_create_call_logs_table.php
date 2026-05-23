<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caller_id');
            $table->unsignedBigInteger('receiver_id');
            $table->string('channel_name');
            $table->enum('type', ['voice', 'video'])->default('voice');
            $table->enum('status', ['missed', 'answered', 'declined'])->default('missed');
            $table->integer('duration')->default(0); // in seconds
            $table->timestamps();

            $table->foreign('caller_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['caller_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
