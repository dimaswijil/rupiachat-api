<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_read')->default(0)->after('media_size');
            $table->index(['room_id', 'sender_id', 'is_read']); // Index untuk query markAsRead
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['room_id', 'sender_id', 'is_read']);
            $table->dropColumn('is_read');
        });
    }
};
