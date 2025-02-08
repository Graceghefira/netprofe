<?php

namespace App\Http\Controllers;

use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class AuthController extends BaseMikrotikController
{

    public function switchEndpoint(Request $request)
{
    $endpoint = $request->input('endpoint', 'primary');

    try {
        // Simpan endpoint ke cache
        Cache::put('global_endpoint', $endpoint);

        $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
        $hotspotController->getHotspotUsers1();
        $hotspotController->getHotspotProfile();
        $hotspotController->getRoutes();
        $hotspotController->getNetwatch();


        return response()->json([
            'message' => "Endpoint switched to $endpoint successfully.",
            'current_endpoint' => $endpoint,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
    }


    public function getInterfaces() {
        try {
            // Inisialisasi koneksi ke Mikrotik
            $endpoint = Cache::get('global_endpoint');
            $client = $this->getClient($endpoint);

            // Query untuk mendapatkan daftar interface
            $query = (new Query('/interface/print'));

            // Eksekusi query
            $interfaces = $client->query($query)->read();

            // Debug: Cetak seluruh data yang diterima
            Log::info("Interfaces: " . print_r($interfaces, true));

            return response()->json($interfaces, 200);
        } catch (\Exception $e) {
            Log::error('Error saat getInterfaces: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
