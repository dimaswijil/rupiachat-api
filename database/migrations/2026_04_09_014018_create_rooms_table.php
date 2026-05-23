<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_id');        // contoh: "10_11"
            $table->unsignedBigInteger('user_id'); // siapa yang arsipkan
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->unique(['room_id', 'user_id']); // 1 user 1 room = 1 record
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};