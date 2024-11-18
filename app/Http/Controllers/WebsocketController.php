<?php

namespace App\Http\Controllers;
use App\Events\LeaseFetched;
use App\Events\LogFetched;
use App\Events\TestEvent;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;
use App\Events\LogUpdated;
use Illuminate\Support\Facades\Log;
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

    public function sendMessage(Request $request)
    {
        $message = $request->input('message', 'Hello, this is a test message!');

        // Log untuk memastikan fungsi dipanggil
        Log::info('Sending message event', ['message' => $message]);

        // Kirim event
        event(new TestEvent($message));

        return response()->json(['status' => 'Message sent!']);
    }

    public function getLogs1()
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

    public function getLeasesRealtime()
{
    $client = $this->getClient();

    try {
        $query = new Query('/ip/dhcp-server/lease/print');
        $leases = $client->query($query)->read();

        $previousLeases = cache()->get('previous_leases');

        if ($previousLeases !== $leases) {
            cache()->put('previous_leases', $leases);

            // Tambahkan log untuk pengecekan
            Log::info('Lease data updated and event broadcasted.', ['leases' => $leases]);

            broadcast(new LeaseFetched($leases));
        }

        return response()->json([
            'status' => 'success',
            'leases' => $leases,
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to fetch leases: ' . $e->getMessage());

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch leases: ' . $e->getMessage(),
        ], 500);
    }
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

        // Kirim event dengan data log
        broadcast(new LogFetched($limitedLogs));

        // Mengembalikan 5 log terbaru dalam format JSON
        return response()->json([
            'logs' => $limitedLogs,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }



}
