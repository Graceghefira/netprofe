<?php
namespace App\Http\Controllers;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;
use App\Models\AkunKantor;
use App\Models\Menu;
use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LinkController extends Controller
{
    protected function getClient()
    {
        $config = [
            'host' => 'id-4.hostddns.us',  // Ganti dengan domain DDNS kamu
            'user' => 'admin',             // Username Mikrotik
            'pass' => 'admin2',            // Password Mikrotik
            'port' => 21326,                // Port API Mikrotik (default 8728)
        ];

        return new Client($config);
    }

    public function getKidsControlDevices()
{
    try {
        $client = $this->getClient();
        $devices = $client->query(new Query('/ip/kid-control/device/print'))->read();

        $processedDevices = array_map(fn($device) => [
            'name' => $device['name'] ?? 'unknown',
            'mac_address' => $device['mac-address'] ?? 'unknown',
            'user' => $device['user'] ?? 'unknown',
            'ip_address' => $device['address'] ?? 'unknown',
            'rate_up' => $device['rate-up'] ?? 'unknown',
            'rate_down' => $device['rate-down'] ?? 'unknown',
            'bytes_up' => $device['bytes-up'] ?? 0,
            'bytes_down' => $device['bytes-down'] ?? 0,
            'activity' => isset($device['activity']) ? $this->getDomainName($device['activity']) : 'unknown',
        ], $devices);

        return response()->json([
            'total_devices' => count($processedDevices),
            'devices' => $processedDevices,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

// Fungsi tambahan untuk mendapatkan nama domain utama saja
    private function getDomainName($url)
    {
        // Menghapus protokol (http, https) jika ada
        $url = preg_replace('/^https?:\/\//', '', $url);

        // Mengambil bagian domain dari URL
        $domain = parse_url('http://' . $url, PHP_URL_HOST);

        // Memecah domain menjadi bagian-bagian untuk mengambil domain utama saja
        $parts = explode('.', $domain);
        $count = count($parts);

        // Jika domain memiliki lebih dari dua bagian, ambil dua bagian terakhir
        if ($count > 2) {
            return $parts[$count - 2] . '.' . $parts[$count - 1];
        }

        return $domain;
    }



}
