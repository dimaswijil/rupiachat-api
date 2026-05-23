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
        Schema::table('call_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('receiver_id');
            $table->string('group_name')->nullable()->after('group_id');
        });

        // Make receiver_id nullable for group calls
        Schema::table('call_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('receiver_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropColumn(['group_id', 'group_name']);
        });
    }
};
