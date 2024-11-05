<?php
use App\Http\Controllers\MikrotikController;
use Illuminate\Support\Facades\Route;
use App\Events\LogUpdated;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-broadcast', function () {
    event(new LogUpdated(['message' => 'Tes log dari Laravel!']));
    return 'Event broadcasted!';
});

Route::get('/mikrotik-connect', [MikrotikController::class, 'connectToMikrotik']);
Route::get('/check-connection', [MikrotikController::class, 'checkConnection']);
Route::get('/login-hotspot-user', [MikrotikController::class, 'loginHotspotUser1']);

Route::get('/test-hotspot-login', [MikrotikController::class, 'showTestPage']);
Route::post('/test-hotspot-login', [MikrotikController::class, 'testHotspotLogin']);
