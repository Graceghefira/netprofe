<?php

use App\Events\LeaseFetched;
use App\Http\Controllers\MikrotikController;
use Illuminate\Support\Facades\Route;
use App\Events\LogUpdated;
use PhpMqtt\Client\Facades\MQTT;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/index', function () {
    return view('index');
});

