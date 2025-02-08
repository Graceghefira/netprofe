<?php

namespace App\Http\Controllers;
use RouterOS\Client;  // Pastikan sudah mengimpor class Client
use RouterOS\Query;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceMikrotikController extends Controller
{
    /**
     * Fungsi untuk mendapatkan koneksi client MikroTik
     */
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

    /**
     * Menampilkan daftar interface MikroTik
     */
    public function getInterfaces()
    {
        try {
            $client = $this->getClient();

            // Mengambil data interface dari MikroTik
            $query = new Query('/interface/print');
            $data = $client->query($query)->read();


            return response()->json($data); // Menampilkan hasilnya dalam bentuk JSON
        } catch (\Exception $e) {
            Log::error('Error getting interfaces: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get interfaces from MikroTik'], 500);
        }
    }

    /**
     * Menambahkan konfigurasi WireGuard pada MikroTik
     */
    public function createWireGuardConfig(Request $request)
    {
        $privateKey = $request->input('private_key');
        $listenPort = $request->input('listen_port', 13231);  // Default port 13231

        try {
            $client = $this->getClient();

            // Membuat konfigurasi WireGuard pada MikroTik
            $query = new Query('/interface/wireguard/add');
            $query->equal('name', 'wg0');
            $query->equal('listen-port', $listenPort);
            $query->equal('private-key', $privateKey);

            // Eksekusi query untuk membuat interface WireGuard
            $client->query($query);

            // Menambahkan alamat IP ke interface WireGuard
            $query = new Query('/ip/address/add');
            $query->equal('address', '10.10.10.1/24');
            $query->equal('interface', 'wg0');
            $client->query($query);

            // Mengaktifkan WireGuard
            $query = new Query('/interface/wireguard/enable');
            $query->equal('numbers', '0');  // Mengaktifkan interface pertama (wg0)
            $client->query($query);

            return response()->json([
                'message' => 'WireGuard interface created and enabled successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating WireGuard config: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create WireGuard config'], 500);
        }
    }

    /**
     * Menambahkan Peer WireGuard ke MikroTik
     */
    public function addWireGuardPeer(Request $request)
    {
        $publicKey = $request->input('public_key');
        $endpoint = $request->input('endpoint');
        $allowedIPs = $request->input('allowed_ips');

        try {
            $client = $this->getClient();

            // Query untuk menambahkan peer WireGuard
            $query = new Query('/interface/wireguard/peers/add');
            $query->equal('public-key', $publicKey);
            $query->equal('endpoint-address', $endpoint);
            $query->equal('endpoint-port', 13231);  // Port yang sesuai
            $query->equal('allowed-address', $allowedIPs);

            // Eksekusi query untuk menambahkan peer
            $client->query($query);

            return response()->json([
                'message' => 'WireGuard peer added successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding WireGuard peer: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to add WireGuard peer'], 500);
        }
    }

    /**
     * Mengaktifkan koneksi WireGuard pada MikroTik
     */
    public function enableWireGuard()
    {
        try {
            $client = $this->getClient();

            // Mengaktifkan WireGuard
            $query = new Query('/interface/wireguard/enable');
            $query->equal('numbers', '0');  // Mengaktifkan interface pertama (wg0)
            $client->query($query);

            return response()->json([
                'message' => 'WireGuard connection enabled successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error enabling WireGuard: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to enable WireGuard'], 500);
        }
    }

    /**
     * Menonaktifkan koneksi WireGuard pada MikroTik
     */
    public function disableWireGuard()
    {
        try {
            $client = $this->getClient();

            // Menonaktifkan WireGuard
            $query = new Query('/interface/wireguard/disable');
            $query->equal('numbers', '0');  // Menonaktifkan interface pertama (wg0)
            $client->query($query);

            return response()->json([
                'message' => 'WireGuard connection disabled successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error disabling WireGuard: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to disable WireGuard'], 500);
        }
    }
}
