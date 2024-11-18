<?php

namespace App\Http\Controllers;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;

class TerminalController extends Controller
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

        public function executeMikrotikCommand(Request $request)
{
    try {
        // Ambil perintah dari request
        $command = $request->input('command');

        // Inisialisasi koneksi ke Mikrotik
        $client = $this->getClient();

        // Membuat query berdasarkan perintah yang diberikan
        $query = new Query($command);

        // Menjalankan perintah di terminal Mikrotik
        $response = $client->q($query)->read();

        // Kembalikan hasil dalam format JSON
        return response()->json(['result' => $response], 200);

    } catch (\Exception $e) {
        // Tangani kesalahan dan kembalikan pesan error
        return response()->json(['error' => 'Mikrotik command execution failed: ' . $e->getMessage()], 500);
    }
        }

        public function executeCmdCommand(Request $request)
{
    try {
        // Ambil perintah dari request
        $command = $request->input('command');
        $output = [];
        $return_var = 0;

        // Batasi waktu eksekusi maksimum
        set_time_limit(30); // Maksimum 30 detik

        // Menjalankan perintah dengan pengaturan timeout
        exec($command, $output, $return_var);

        // Periksa apakah perintah berhasil dijalankan
        if ($return_var !== 0) {
            return response()->json(['error' => 'Command execution failed: ' . implode("\n", $output)], 500);
        }

        // Kembalikan hasil dalam format JSON
        return response()->json(['result' => $output], 200);

    } catch (\Exception $e) {
        // Tangani kesalahan dan kembalikan pesan error
        return response()->json(['error' => 'CMD command execution failed: ' . $e->getMessage()], 500);
    }
        }

        public function getRouterInfo()
        {
            try {
                // Inisialisasi koneksi ke Mikrotik
                $client = $this->getClient();

                // Mengambil informasi system resource
                $resourceQuery = new Query('/system/resource/print');
                $resourceData = $client->q($resourceQuery)->read();

                // Mengambil informasi uptime dan waktu
                $timeQuery = new Query('/system/clock/print');
                $timeData = $client->q($timeQuery)->read();

                // Pengecekan apakah data 'voltage' dan 'temperature' tersedia
                $voltage = isset($resourceData[0]['voltage']) ? $resourceData[0]['voltage'] . 'V' : 'Not Available';
                $temperature = isset($resourceData[0]['temperature']) ? $resourceData[0]['temperature'] . 'C' : 'Not Available';

                // Menyusun informasi yang diambil dari API
                $response = [
                    'time' => $timeData[0]['time'] ?? null,
                    'date' => $timeData[0]['date'] ?? null,
                    'uptime' => $resourceData[0]['uptime'] ?? null,
                    'cpu_load' => $resourceData[0]['cpu-load'] . '%' ?? null,
                    'free_memory' => round($resourceData[0]['free-memory'] / (1024 * 1024), 1) . ' MiB' ?? null,
                    'total_memory' => round($resourceData[0]['total-memory'] / (1024 * 1024), 1) . ' MiB' ?? null,
                    'free_hdd' => round($resourceData[0]['free-hdd-space'] / (1024 * 1024), 1) . ' MiB' ?? null,
                    'total_hdd' => round($resourceData[0]['total-hdd-space'] / (1024 * 1024), 1) . ' MiB' ?? null,
                    'sector_writes' => $resourceData[0]['write-sect-since-reboot'] ?? null,
                    'bad_blocks' => isset($resourceData[0]['bad-blocks']) ? $resourceData[0]['bad-blocks'] . '%' : '0%',
                    'cpu_architecture' => $resourceData[0]['architecture-name'] ?? null,
                    'board_name' => $resourceData[0]['board-name'] ?? null,
                    'router_os' => $resourceData[0]['version'] ?? null,
                    'build_time' => $resourceData[0]['build-time'] ?? 'Not Available',  // Diambil dari /system/resource/print
                    'factory_software' => $resourceData[0]['factory-software'] ?? 'Not Available', // Diambil dari /system/resource/print
                ];

                // Mengembalikan hasil dalam format JSON
                return response()->json($response, 200);

            } catch (\Exception $e) {
                // Tangani kesalahan dan kembalikan pesan error
                return response()->json(['error' => 'Failed to fetch router info: ' . $e->getMessage()], 500);
            }
        }


}
