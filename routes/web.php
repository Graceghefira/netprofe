<?php

use App\Events\LeaseFetched;
use App\Http\Controllers\MikrotikController;
use Illuminate\Support\Facades\Route;
use App\Events\LogUpdated;
use PhpMqtt\Client\Facades\MQTT;

Route::get('/', function () {
    return view('welcome');


Route::prefix('api-netpro')->group(function () {

    Route::get('/', function () {
        return response()->json(['message' => 'API Netprofe is working!']);
//return response()->json(['message' => $_SERVER);
    });

    Route::get('/index', function () {
       return view('index');
    });


    Route::get('/mikrotik-connect', [MikrotikController::class, 'connectToMikrotik']);
    Route::get('/check-connection', [MikrotikController::class, 'checkConnection']);
    Route::get('/login-hotspot-user', [MikrotikController::class, 'loginHotspotUser1']);
    Route::get('/test-hotspot-login', [MikrotikController::class, 'showTestPage']);
    Route::post('/test-hotspot-login', [MikrotikController::class, 'testHotspotLogin']);
});

Route::get('/index', function () {
    return 'hello world';
});

Route::get('/test-mqtt', function () {
    try {
        MQTT::publish('test/topic', 'Pesan uji koneksi MQTT');
        return 'Pesan berhasil dikirim ke broker MQTT.';
    } catch (\Exception $e) {
        return 'Gagal mengirim pesan: ' . $e->getMessage();
    }
});

Route::get('/mikrotik-connect', [MikrotikController::class, 'connectToMikrotik']);
Route::get('/check-connection', [MikrotikController::class, 'checkConnection']);
Route::get('/login-hotspot-user', [MikrotikController::class, 'loginHotspotUser1']);
Route::get('/test-hotspot-login', [MikrotikController::class, 'showTestPage']);
Route::post('/test-hotspot-login', [MikrotikController::class, 'testHotspotLogin']);
