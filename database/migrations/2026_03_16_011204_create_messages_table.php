<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('room_id'); // contoh: "1_2"
            $table->unsignedBigInteger('sender_id');
            $table->text('text')->nullable();
            $table->string('type')->default('text'); // text / payment
            $table->string('amount')->nullable(); // untuk payment
            $table->timestamps();

            $table->foreign('sender_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Index untuk query pesan per room lebih cepat
            $table->index('room_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};