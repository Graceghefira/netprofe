<?php

namespace App\Http\Controllers;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;


class FailOverController extends Controller
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

    private function setupRoutingFailover($client, $gatewayMain, $gatewayBackup, $metricMain = 1, $metricBackup = 2, $pingCheck = 'ping')
{
    // Mengatur routing failover untuk gateway utama
    $query = new Query('/ip/route/add');
    $query->equal('gateway', $gatewayMain)
        ->equal('distance', $metricMain)
        ->equal('check-gateway', $pingCheck);
    $client->query($query);

    // Mengatur routing failover untuk gateway cadangan
    $query = new Query('/ip/route/add');
    $query->equal('gateway', $gatewayBackup)
        ->equal('distance', $metricBackup)
        ->equal('check-gateway', $pingCheck);
    $client->query($query);
    }

    private function setupNetwatchFailover($client, $gatewayMain, $gatewayBackup, $interval = '10s', $timeout = '1s')
    {
        // Menambahkan Netwatch untuk memantau gateway utama
        $query = new Query('/tool/netwatch/add');
        $query->equal('host', $gatewayMain)
            ->equal('interval', $interval)
            ->equal('timeout', $timeout)
            ->equal('up-script', '/ip route enable [find gateway=' . $gatewayMain . ']')
            ->equal('down-script', '/ip route disable [find gateway=' . $gatewayMain . ']');
        $client->query($query);

        // Menambahkan Netwatch untuk memantau gateway cadangan
        $query = new Query('/tool/netwatch/add');
        $query->equal('host', $gatewayBackup)
            ->equal('interval', $interval)
            ->equal('timeout', $timeout)
            ->equal('up-script', '/ip route enable [find gateway=' . $gatewayBackup . ']')
            ->equal('down-script', '/ip route disable [find gateway=' . $gatewayBackup . ']');
        $client->query($query);
    }

    public function addFailoverData(Request $request)
    {
        // Validasi input data
        $request->validate([
            'gateway_main' => 'required|ip',
            'gateway_backup' => 'required|ip',
            'metric_main' => 'required|integer|min:1',
            'metric_backup' => 'required|integer|min:2',
            'interval' => 'nullable|string|in:5s,10s,20s,30s', // Pilihan interval Netwatch
            'timeout' => 'nullable|string|in:1s,2s,3s,5s', // Pilihan timeout Netwatch
        ]);

        // Ambil data dari input
        $gatewayMain = $request->input('gateway_main');
        $gatewayBackup = $request->input('gateway_backup');
        $metricMain = $request->input('metric_main', 1);  // Default metric untuk gateway utama adalah 1
        $metricBackup = $request->input('metric_backup', 2);  // Default metric untuk gateway cadangan adalah 2
        $interval = $request->input('interval', '10s');  // Default interval Netwatch
        $timeout = $request->input('timeout', '1s');  // Default timeout Netwatch

        try {
            // Siapkan client MikroTik
            $client = $this->getClient();

            // Tambahkan routing failover (routing utama dan cadangan)
            $this->setupRoutingFailover($client, $gatewayMain, $gatewayBackup, $metricMain, $metricBackup);

            // Tambahkan Netwatch failover untuk monitoring kedua gateway
            $this->setupNetwatchFailover($client, $gatewayMain, $gatewayBackup, $interval, $timeout);

            // Simpan data failover baru ke database atau tempat penyimpanan lainnya jika diperlukan
            // Misalnya menyimpan data ke tabel `failover_gateways`
            // FailoverGateway::create([
            //     'gateway_main' => $gatewayMain,
            //     'gateway_backup' => $gatewayBackup,
            //     'metric_main' => $metricMain,
            //     'metric_backup' => $metricBackup,
            //     'interval' => $interval,
            //     'timeout' => $timeout
            // ]);

            return response()->json([
                'message' => 'Data failover berhasil ditambahkan.',
                'data' => [
                    'gateway_main' => $gatewayMain,
                    'gateway_backup' => $gatewayBackup,
                    'metric_main' => $metricMain,
                    'metric_backup' => $metricBackup,
                    'interval' => $interval,
                    'timeout' => $timeout,
                ]
            ]);
        } catch (\Exception $e) {
            // Jika terjadi error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getRoute()
    {
        // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
        $client = $this->getClient();

        try {
            // Buat query untuk mengambil data leases dari DHCP server
            $query = new Query('/ip/route/print');

            // Jalankan query
            $leases = $client->query($query)->read();

            // Kembalikan response ke user
            return response()->json([
                'status' => 'success',
                'leases' => $leases
            ]);

        } catch (\Exception $e) {
            // Tangani error jika ada
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch leases: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteFailoverData(Request $request)
{
    // Validasi input
    $request->validate([
        'gateway_main' => 'required|ip',
        'gateway_backup' => 'required|ip',
    ]);

    $gatewayMain = $request->input('gateway_main');
    $gatewayBackup = $request->input('gateway_backup');

    try {
        // Ambil client MikroTik
        $client = $this->getClient();

        // Cek apakah route dengan gateway utama ada
        $routeMainQuery = (new Query('/ip/route/print'))->where('gateway', $gatewayMain);
        $routeMain = $client->query($routeMainQuery)->read();

        // Cek apakah route dengan gateway cadangan ada
        $routeBackupQuery = (new Query('/ip/route/print'))->where('gateway', $gatewayBackup);
        $routeBackup = $client->query($routeBackupQuery)->read();

        // Jika route utama ditemukan, hapus
        if (!empty($routeMain)) {
            $deleteRouteMainQuery = (new Query('/ip/route/remove'))->equal('.id', $routeMain[0]['.id']);
            $client->query($deleteRouteMainQuery)->read();
        }

        // Jika route cadangan ditemukan, hapus
        if (!empty($routeBackup)) {
            $deleteRouteBackupQuery = (new Query('/ip/route/remove'))->equal('.id', $routeBackup[0]['.id']);
            $client->query($deleteRouteBackupQuery)->read();
        }

        // Cek apakah Netwatch dengan gateway utama ada
        $netwatchMainQuery = (new Query('/tool/netwatch/print'))->where('host', $gatewayMain);
        $netwatchMain = $client->query($netwatchMainQuery)->read();

        // Cek apakah Netwatch dengan gateway cadangan ada
        $netwatchBackupQuery = (new Query('/tool/netwatch/print'))->where('host', $gatewayBackup);
        $netwatchBackup = $client->query($netwatchBackupQuery)->read();

        // Jika Netwatch utama ditemukan, hapus
        if (!empty($netwatchMain)) {
            $deleteNetwatchMainQuery = (new Query('/tool/netwatch/remove'))->equal('.id', $netwatchMain[0]['.id']);
            $client->query($deleteNetwatchMainQuery)->read();
        }

        // Jika Netwatch cadangan ditemukan, hapus
        if (!empty($netwatchBackup)) {
            $deleteNetwatchBackupQuery = (new Query('/tool/netwatch/remove'))->equal('.id', $netwatchBackup[0]['.id']);
            $client->query($deleteNetwatchBackupQuery)->read();
        }

        return response()->json([
            'message' => 'Konfigurasi failover berhasil dihapus.',
            'data' => [
                'gateway_main' => $gatewayMain,
                'gateway_backup' => $gatewayBackup,
            ]
        ]);
    } catch (\Exception $e) {
        // Jika terjadi error, kembalikan pesan error
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }



}
