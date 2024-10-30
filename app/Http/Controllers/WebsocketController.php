<?php

namespace App\Http\Controllers;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;
use App\Events\LogUpdated;
use PhpMqtt\Client\Facades\MQTT;

class WebsocketController extends Controller
{
    protected function getClient()
{
    // Konfigurasi Mikrotik (sesuaikan dengan Mikrotik Anda)
    $config = [
        'host' => 'id-4.hostddns.us',  // Ganti dengan domain DDNS kamu
        'user' => 'admin',             // Username Mikrotik
        'pass' => 'admin2',            // Password Mikrotik
        'port' => 21326,               // Port API Mikrotik (default 8728)
    ];

    // Mengembalikan client RouterOS yang siap digunakan
    return new Client($config);
    }

    public function sendLogUpdate()
    {
        $logs = ['message' => 'Ini adalah log baru dari Laravel'];

        // Menyiarkan event
        broadcast(new LogUpdated($logs));
    }

    public function getLogs()
    {
        try {
            // Inisialisasi client
            $client = $this->getClient();

            // Query untuk mengambil log dari MikroTik
            $logQuery = new Query('/log/print');
            $logs = $client->query($logQuery)->read();

            // Urutkan log dari terbaru ke terlama
            $logs = array_reverse($logs);

            // Ambil hanya 5 log terbaru
            $limitedLogs = array_slice($logs, 0, 5);

            // Mengembalikan 5 log terbaru dalam format JSON
            return response()->json([
                'logs' => $limitedLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }





}
