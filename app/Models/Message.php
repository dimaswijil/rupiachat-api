<?php
// ============================================================
// FILE 1: app/Models/Message.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'room_id',    // ID ruang chat (gabungan 2 uid)
        'sender_id',  // ID pengirim
        'text',       // isi pesan
        'type',       // 'text', 'payment', 'image', 'video', 'audio', 'file'
        'amount',     // nominal (hanya untuk payment)
        'media_url',  // URL/path file media
        'media_type', // MIME type file
        'media_name', // Nama asli file
        'media_size', // Ukuran file (bytes)
        'is_read',    // FIX: ditambah

    ];

    // Relasi ke User (pengirim)
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}


// ============================================================
// FILE 2: database/migrations/xxxx_create_messages_table.php
// Buat dengan: php artisan make:migration create_messages_table
// ============================================================

// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;
//
// return new class extends Migration {
//     public function up(): void
//     {
//         Schema::create('messages', function (Blueprint $table) {
//             $table->id();
//             $table->string('room_id');        // contoh: "1_2"
//             $table->unsignedBigInteger('sender_id');
//             $table->text('text')->nullable();
//             $table->string('type')->default('text'); // text / payment
//             $table->string('amount')->nullable();    // untuk payment
//             $table->timestamps();
//
//             $table->foreign('sender_id')
//                   ->references('id')
//                   ->on('users')
//                   ->onDelete('cascade');
//
//             // Index untuk query pesan per room lebih cepat
//             $table->index('room_id');
//         });
//     }
//
//     public function down(): void
//     {
//         Schema::dropIfExists('messages');
//     }
// };
