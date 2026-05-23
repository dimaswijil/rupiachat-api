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
            // URL/path file media yang diupload
            $table->string('media_url')->nullable()->after('text');

            // MIME type file (image/jpeg, video/mp4, audio/mpeg, application/pdf, dll)
            $table->string('media_type')->nullable()->after('media_url');

            // Nama asli file yang diupload
            $table->string('media_name')->nullable()->after('media_type');

            // Ukuran file dalam bytes
            $table->unsignedBigInteger('media_size')->nullable()->after('media_name');

            // Update komentar kolom type: text / payment / image / video / audio / file
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'media_type', 'media_name', 'media_size']);
        });
    }
};
