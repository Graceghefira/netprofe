<?php

use App\Events\LeaseFetched;
use App\Http\Controllers\MikrotikController;
use Illuminate\Support\Facades\Route;
use App\Events\LogUpdated;


Route::prefix('api-netpro')->group(function () {

    Route::get('/', function () {
        return response()->json(['message' => 'API Netprofe is working!']);
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
