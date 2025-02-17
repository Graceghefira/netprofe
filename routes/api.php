<?php

use App\Http\Controllers\ArtisanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ByteController;
use App\Http\Controllers\DHCPController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\WhoopsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/mikrotik/add-hotspot-profile', [WhoopsController::class, 'setHotspotProfile']);
Route::post('/mikrotik/add-hotspot-login-Annual', [WhoopsController::class, 'addHotspotUser1']);
Route::Delete('/mikrotik/delete', [WhoopsController::class, 'deleteExpiredHotspotUsers']);
Route::post('/mikrotik/update-data', [WhoopsController::class, 'UpdateData']);
Route::post('/mikrotik/update-status', [WhoopsController::class, 'updateAllHotspotUsersByPhoneNumber']);
Route::get('/mikrotik/list-voucher', [WhoopsController::class, 'getVoucherLists']);
Route::get('/mikrotik/list-akun', [WhoopsController::class, 'getHotspotUsers']);

Route::post('/mikrotik/run-migrations', [ArtisanController::class, 'runMigrations']);
Route::post('/mikrotik/run-rollback', [ArtisanController::class, 'runrollback']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/regis', [AuthController::class, 'register']);
Route::get('/Email', [AuthController::class, 'GetEmail']);

Route::get('/mikrotik/get-data-users', [ByteController::class, 'getHotspotUsers']);
Route::post('/mikrotik/get-data-by-date', [ByteController::class, 'getHotspotUsersByDateRange1']);
Route::post('/mikrotik/get-data-by-date-pagi', [ByteController::class, 'getHotspotUsersByDateRangeWithLoginCheck']);
Route::post('/mikrotik/get-data-by-date-role', [ByteController::class, 'getHotspotUsersByUniqueRole']);
Route::get('/mikrotik/get-data-all-profile', [ByteController::class, 'getHotspotProfile']);
Route::delete('/mikrotik/deleteExpiredHotspotUsersByPhone/{no_hp}', [ByteController::class, 'deleteHotspotUserByPhoneNumber']);

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

Route::post('/mikrotik/upload-file', [FileController::class, 'uploadFileToMikrotik']);
Route::get('/mikrotik/list-file', [FileController::class, 'listFilesOnMikrotik']);
Route::get('/mikrotik/download-file/{fileName}', [FileController::class, 'downloadFileFromMikrotik'])->where('fileName', '.*');
Route::delete('/mikrotik/delete-file/{fileName}', [FileController::class, 'deleteFileOnMikrotik'])->where('fileName', '.*');

Route::get('/mikrotik/get-profile', [HotspotProfileController::class, 'getHotspotProfile']);
Route::get('/mikrotik/get-profile/{profile_name}', [HotspotProfileController::class, 'getHotspotProfileByName']);
Route::post('/mikrotik/hotspot-profile/{profile_name}', [HotspotProfileController::class, 'updateHotspotProfile']);
Route::post('/mikrotik/set-profile', [HotspotProfileController::class, 'setHotspotProfile']);
Route::delete('/mikrotik/delete-profile/{profile_name}', [HotspotProfileController::class, 'deleteHotspotProfile']);

Route::post('/mikrotik/add', [MenuController::class, 'addMenu']);
Route::put('/mikrotik/edit/{id}', [MenuController::class, 'editMenu']);
Route::get('/mikrotik/get-all-menu', [MenuController::class, 'getAllMenus']);
Route::get('/mikrotik/get-all-order', [MenuController::class, 'getAllOrders']);

Route::post('/mikrotik/add-Hotspot-User', [MikrotikController::class, 'addHotspotUser']);
Route::post('/mikrotik/add-hotspot-login', [MikrotikController::class, 'addHotspotUser1']);
Route::post('/mikrotik/add-hotspot-login-by-time', [MikrotikController::class, 'addHotspotUserByExpiryTime']);
Route::post('/mikrotik/get-Hotspot-test', [MikrotikController::class, 'updateAllHotspotUsersByPhoneNumber']);
Route::get('/mikrotik/get-Hotspot-users-byte', [MikrotikController::class, 'getHotspotUsersByte']);
Route::get('/mikrotik/get-Hotspot-users/{profile_name}', [MikrotikController::class, 'getHotspotUsersByProfileName']);
Route::get('/mikrotik/get-Hotspot-by-phone/{no_hp}', [MikrotikController::class, 'getHotspotUserByPhoneNumber']);
Route::post('/mikrotik/hotspot-user/{no_hp}', [MikrotikController::class, 'editHotspotUser']);
Route::post('/mikrotik/update-user', [MikrotikController::class, 'updateAllHotspotUsersByPhoneNumber']);

Route::get('/mikrotik/Router-info', [TerminalController::class, 'getRouterInfo']);
Route::post('/mikrotik/terminal-mikrotik', [TerminalController::class, 'executeMikrotikCommand']);
Route::post('/mikrotik/terminal-cmd', [TerminalController::class, 'executeCmdCommand']);



Route::middleware(['auth:sanctum','role:admin'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

