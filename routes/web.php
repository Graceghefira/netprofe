<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-encryption', function () {
    // Cek jika data terenkripsi bisa didekripsi dengan benar
    $tenantId = 'yana'; // Contoh tenant ID yang ingin dienkripsi
    $encrypted = Crypt::encryptString($tenantId);  // Mengenkripsi tenant ID
    $decrypted = Crypt::decryptString($encrypted); // Mendekripsi tenant ID

    // Debug: Periksa apakah dekripsi menghasilkan tenantId yang benar
    dd($encrypted, $decrypted);
});
