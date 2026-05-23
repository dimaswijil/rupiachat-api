<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('sender_id');
            $table->text('text')->nullable();
            $table->string('type')->default('text'); // text, image, payment
            $table->string('amount')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_type')->nullable();
            $table->string('media_name')->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->timestamps();

            $table->foreign('group_id')
                  ->references('id')
                  ->on('groups')
                  ->onDelete('cascade');

            $table->foreign('sender_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_messages');
    }
};
