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
        Schema::table('rooms', function (Blueprint $table) {
            $table->timestamp('last_cleared_at')->nullable()->after('is_archived');
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->timestamp('last_cleared_at')->nullable()->after('joined_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('last_cleared_at');
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn('last_cleared_at');
        });
    }
};
