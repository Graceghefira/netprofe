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

Schedule::call(function () {
    $controller = new \App\Http\Controllers\MikrotikController();
    $controller->updateAllHotspotUsersByPhoneNumber();
})->everyFiveSeconds();

Schedule::call(function () {
    $controller = new \App\Http\Controllers\MqttController();
    $controller->getHotspotUsers1();
})->everyFiveSeconds();
