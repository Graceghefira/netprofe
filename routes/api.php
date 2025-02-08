<?php

use App\Http\Controllers\AddUserController;
use App\Http\Controllers\AnnualController;
use App\Http\Controllers\ArtisanController;
use App\Http\Controllers\MikrotikController;
use App\Http\Controllers\MikroTikWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BaseMikrotikController;
use App\Http\Controllers\ByteController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DeviceMikrotikController;
use App\Http\Controllers\DHCPController;
use App\Http\Controllers\FailOverController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HotspotProfileController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MqttController;
use App\Http\Controllers\OVPNController;
use App\Http\Controllers\ResponController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\WebBlockController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/mikrotik/add-Hotspot-User', [MikrotikController::class, 'addHotspotUser']);
Route::post('/mikrotik/add-hotspot-login', [MikrotikController::class, 'addHotspotUser1']);
Route::post('/mikrotik/add-hotspot-login-by-time', [MikrotikController::class, 'addHotspotUserByExpiryTime']);
Route::post('/mikrotik/get-Hotspot-test', [MikrotikController::class, 'updateAllHotspotUsersByPhoneNumber']);
Route::get('/mikrotik/get-Hotspot-users-byte', [MikrotikController::class, 'getHotspotUsersByte']);
Route::get('/mikrotik/get-Hotspot-users/{profile_name}', [MikrotikController::class, 'getHotspotUsersByProfileName']);
Route::get('/mikrotik/get-Hotspot-by-phone/{no_hp}', [MikrotikController::class, 'getHotspotUserByPhoneNumber']);
Route::post('/mikrotik/hotspot-user/{no_hp}', [MikrotikController::class, 'editHotspotUser']);
Route::post('/mikrotik/update-user', [MikrotikController::class, 'updateAllHotspotUsersByPhoneNumber']);


Route::post('/mikrotik/add', [MenuController::class, 'addMenu']);
Route::put('/mikrotik/edit/{id}', [MenuController::class, 'editMenu']);
Route::get('/mikrotik/get-all-menu', [MenuController::class, 'getAllMenus']);
Route::get('/mikrotik/get-all-order', [MenuController::class, 'getAllOrders']);

Route::get('/mikrotik/get-profile', [HotspotProfileController::class, 'getHotspotProfile']);
Route::get('/mikrotik/get-profile-Pagi', [HotspotProfileController::class, 'getHotspotProfilePagi']);
Route::get('/mikrotik/get-profile/{profile_name}', [HotspotProfileController::class, 'getHotspotProfileByName']);
Route::post('/mikrotik/hotspot-profile/{profile_name}', [HotspotProfileController::class, 'updateHotspotProfile']);
Route::post('/mikrotik/set-profile', [HotspotProfileController::class, 'setHotspotProfile']);
Route::delete('/mikrotik/delete-profile/{profile_name}', [HotspotProfileController::class, 'deleteHotspotProfile']);

Route::post('/mikrotik/web-block', [WebBlockController::class, 'blockDomain']);
Route::get('/mikrotik/get-web-block', [WebBlockController::class, 'getBlockedWebsites']);
Route::post('/mikrotik/web-unblock', [WebBlockController::class, 'unblockDomain']);

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


Route::post('/mikrotik/switch-endpoint', [AuthController::class, 'switchEndpoint']);


Route::get('/publish-to-mqtt', [MqttController::class, 'getHotspotUsers1']);
Route::get('/connect-to-mqtt', [MqttController::class, 'connectToMqtt']);

Route::get('/mikrotik/route-info', [FailOverController::class, 'getRoute']);
Route::get('/mikrotik/netwach-info', [MqttController::class, 'getNetwatch']);
Route::post('/mikrotik/set-failover', [FailOverController::class, 'addFailoverData']);
Route::delete('/mikrotik/delet-failover', [FailOverController::class, 'deleteFailoverData']);

Route::post('/mikrotik/add-hotspot-profile', [AnnualController::class, 'setHotspotProfile']);
Route::post('/mikrotik/add-hotspot-login-Annual', [AnnualController::class, 'addHotspotUser1']);
Route::Delete('/mikrotik/delete', [AnnualController::class, 'deleteExpiredHotspotUsers']);
Route::post('/mikrotik/update-data', [AnnualController::class, 'UpdateData']);
Route::post('/mikrotik/update-status', [AnnualController::class, 'updateAllHotspotUsersByPhoneNumber']);
Route::get('/mikrotik/list-voucher', [AnnualController::class, 'getVoucherLists']);
Route::get('/mikrotik/list-akun', [AnnualController::class, 'getHotspotUsers']);

Route::get('/mikrotik/get-interface', [DeviceMikrotikController::class, 'getInterfaces']);

Route::get('/check', [BaseMikrotikController::class, 'checkCurrentEndpoint']);

Route::get('/check-update', [ByteController::class, 'updateUserBytesFromMikrotik1']);
Route::post('/check-interfaces', [AuthController::class, 'getHotspotUsersByUniqueRole']);

Route::post('/complaints', [ResponController::class, 'addTicket']);
Route::get('/AllTickets', [ResponController::class, 'getAllTickets']);
Route::post('/tickets/{tracking_id}/status', [ResponController::class, 'updateStatus']);
Route::delete('/tickets/{tracking_id}', [ResponController::class, 'deleteTicket']);
Route::get('/tickets/date-range', [ResponController::class, 'getTicketsByDateRange']); // Route baru untuk date range

Route::get('/mikrotik/test', [OVPNController::class, 'checkConnection']);

Route::post('/mikrotik/run-migrations', [ArtisanController::class, 'runMigrations']);
Route::post('/mikrotik/run-rollback', [ArtisanController::class, 'runrollback']);

Route::post('/mikrotik/update-voucher', [DatabaseController::class, 'updateVoucher']);

