<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    // Logika pengecekan: Apakah user ini berhak masuk ke room ini?
    // Contoh sederhana: return true; (izinkan semua yang login)
    return auth()->check(); 
});