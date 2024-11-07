<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Schedule::call(function () {
    $controller = new \App\Http\Controllers\ByteController();
    $controller->deleteExpiredHotspotUsers();
})->everyMinute();

Schedule::call(function () {
    $controller = new \App\Http\Controllers\ByteController();
    $controller->updateUserBytesFromMikrotik();
})->daily();

 // Menjadwalkan fungsi untuk mengambil dan menyiarkan log setiap menit
Schedule::call(function () {
    $controller = app(\App\Http\Controllers\WebsocketController::class);
    $controller->sendLogUpdate();
})->everyMinute();  // Dijalankan setiap menit (ubah sesuai kebutuhan)
