<?php

use App\Http\Controllers\AddUserController;
use App\Http\Controllers\MikrotikController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ByteController;
use App\Http\Controllers\DHCPController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MqttController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\testMqttConnection;
use App\Http\Controllers\WebBlockController;
use App\Http\Controllers\WebsocketController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/mikrotik/add-Hotspot-User', [MikrotikController::class, 'addHotspotUser']);
Route::post('/mikrotik/add-hotspot-login', [MikrotikController::class, 'addHotspotUser1']);
Route::post('/mikrotik/add-hotspot-login-by-time', [MikrotikController::class, 'addHotspotUserByExpiryTime']);
Route::get('/mikrotik/get-Hotspot-users', [MikrotikController::class, 'getHotspotUsers1']);
Route::post('/mikrotik/get-Hotspot-test', [MikrotikController::class, 'updateAllHotspotUsersByPhoneNumber']);
Route::get('/mikrotik/get-Hotspot-users-byte', [MikrotikController::class, 'getHotspotUsersByte']);
Route::get('/mikrotik/get-Hotspot-users/{profile_name}', [MikrotikController::class, 'getHotspotUsersByProfileName']);
Route::get('/mikrotik/get-Hotspot-by-phone/{no_hp}', [MikrotikController::class, 'getHotspotUserByPhoneNumber']);
Route::post('/mikrotik/hotspot-user/{no_hp}', [MikrotikController::class, 'editHotspotUser']);

Route::post('/mikrotik/add', [MenuController::class, 'addMenu']);
Route::put('/mikrotik/edit/{id}', [MenuController::class, 'editMenu']);
Route::get('/mikrotik/get-all-menu', [MenuController::class, 'getAllMenus']);
Route::get('/mikrotik/get-all-order', [MenuController::class, 'getAllOrders']);

Route::get('/mikrotik/get-profile', [HotspotProfileController::class, 'getHotspotProfile']);
Route::get('/mikrotik/get-profile-Pagi', [HotspotProfileController::class, 'getHotspotProfilePagi']);
Route::get('/mikrotik/get-profile/{profile_name}', [HotspotProfileController::class, 'getHotspotProfileByName']);
Route::post('/mikrotik/hotspot-profile/{profile_name}', [HotspotProfileController::class, 'editHotspotProfile']);
Route::post('/mikrotik/set-profile', [HotspotProfileController::class, 'setHotspotProfile']);
Route::delete('/mikrotik/delete-profile/{profile_name}', [HotspotProfileController::class, 'deleteHotspotProfile']);

Route::post('/mikrotik/web-block', [WebBlockController::class, 'blockWebsite']);
Route::post('/mikrotik/web-block-alternate', [WebBlockController::class, 'blockWebsite1']);
Route::get('/mikrotik/get-web-block', [WebBlockController::class, 'getBlockedWebsites']);
Route::delete('/mikrotik/web-unblock', [WebBlockController::class, 'unblockWebsite']);
Route::delete('/mikrotik/web-unblock/{domain?}', [WebBlockController::class, 'unblockWebsite1']);

Route::post('/mikrotik/upload-file', [FileController::class, 'uploadFileToMikrotik']);
Route::get('/mikrotik/list-file', [FileController::class, 'listFilesOnMikrotik']);
Route::get('/mikrotik/download-file/{fileName}', [FileController::class, 'downloadFileFromMikrotik'])->where('fileName', '.*');
Route::delete('/mikrotik/delete-file/{fileName}', [FileController::class, 'deleteFileOnMikrotik'])->where('fileName', '.*');


Route::get('/mikrotik/get-data-users', [ByteController::class, 'updateUserBytesFromMikrotik']);
Route::post('/mikrotik/get-data-by-date', [ByteController::class, 'getHotspotUsersByDateRange1']);
Route::post('/mikrotik/get-data-by-date-pagi', [ByteController::class, 'getHotspotUsersByDateRangeWithLoginCheck']);
Route::post('/mikrotik/get-data-by-date-role', [ByteController::class, 'getHotspotUsersByUniqueRole']);
Route::get('/mikrotik/get-data-all-profile', [ByteController::class, 'getHotspotProfile']);
Route::delete('/mikrotik/deleteExpiredHotspotUsers', [ByteController::class, 'deleteExpiredHotspotUsers']);
Route::delete('/mikrotik/deleteExpiredHotspotUsersByPhone/{no_hp}', [ByteController::class, 'deleteHotspotUserByPhoneNumber']);

Route::get('/mikrotik/Router-info', [TerminalController::class, 'getRouterInfo']);
Route::post('/mikrotik/terminal-mikrotik', [TerminalController::class, 'executeMikrotikCommand']);
Route::post('/mikrotik/terminal-cmd', [TerminalController::class, 'executeCmdCommand']);

Route::get('/mikrotik/Dhcp-info', [DHCPController::class, 'getDhcpServers']);
Route::get('/mikrotik/Dhcp-info/{name}', [DHCPController::class, 'getDhcpServerByName']);
Route::get('/mikrotik/Network-info', [DHCPController::class, 'getNetworks']);
Route::get('/mikrotik/Network-info/{gateaway}', [DHCPController::class, 'getNetworksByGateway']);
Route::get('/mikrotik/Leases-info', [DHCPController::class, 'getLeases']);
Route::post('/mikrotik/Add-dhcp', [DHCPController::class, 'addOrUpdateDhcp']);
Route::post('/mikrotik/Add-network', [DHCPController::class, 'addOrUpdateNetwork']);
Route::post('/mikrotik/Make-lease', [DHCPController::class, 'makeLeaseStatic']);
Route::delete('/mikrotik/delete-dhcp/{name}', [DHCPController::class, 'deleteDhcpServerByName']);
Route::delete('/mikrotik/delete-network/{gateway}', [DHCPController::class, 'deleteDhcpNetworkByGateway']);
Route::delete('/mikrotik/delete-lease/{address}', [DHCPController::class, 'deleteDhcpLeaseAndIpBindingByAddress']);


Route::get('/mikrotik/get-kid', [LinkController::class, 'getKidsControlDevices']);

Route::post('/mikrotik/login', [AuthController::class, 'loginWithMikrotikUser']);
Route::get('/publish-to-mqtt', [MqttController::class, 'getHotspotUsers1']);
Route::get('/connect-to-mqtt', [MqttController::class, 'connectToMqtt']);
