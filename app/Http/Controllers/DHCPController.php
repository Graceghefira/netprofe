<?php

namespace App\Http\Controllers;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class DHCPController extends Controller
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

    public function addOrUpdateDhcp(Request $request)
{
    // Validasi request untuk memastikan data yang masuk benar
    $request->validate([
        'name' => 'required|string',
        'interface' => 'required|string',
        'lease_time' => 'required|string',
        'address_pool' => 'required|string',
        'add_arp' => 'required|boolean',
    ]);

    // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
    $client = $this->getClient();

    try {
        // Ambil daftar DHCP server dan cek apakah sudah ada yang menggunakan nama yang sama
        $query = new Query('/ip/dhcp-server/print');
        $dhcpServers = $client->query($query)->read();

        $existingDhcp = collect($dhcpServers)->firstWhere('name', $request->name);

        if ($existingDhcp) {
            // Jika DHCP server dengan nama yang sama ditemukan, lakukan update
            $updateQuery = new Query('/ip/dhcp-server/set');
            $updateQuery->equal('numbers', $existingDhcp['.id'])
                ->equal('interface', $request->interface)
                ->equal('lease-time', $request->lease_time)
                ->equal('address-pool', $request->address_pool)
                ->equal('add-arp', $request->add_arp ? 'yes' : 'no');

            $client->query($updateQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'DHCP server updated successfully!',
            ]);
        } else {
            // Jika tidak ditemukan, tambahkan DHCP server baru
            $addQuery = (new Query('/ip/dhcp-server/add'))
                ->equal('name', $request->name)
                ->equal('interface', $request->interface)
                ->equal('lease-time', $request->lease_time)
                ->equal('address-pool', $request->address_pool)
                ->equal('add-arp', $request->add_arp ? 'yes' : 'no');

            $response = $client->query($addQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'DHCP server added successfully!',
                'response' => $response,
            ]);
        }
    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to process DHCP server: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function addOrUpdateNetwork(Request $request)
{
    // Validasi request untuk memastikan data yang masuk benar
    $request->validate([
        'address' => 'required|string',       // Alamat network (contoh: 192.168.1.0/24)
        'gateway' => 'required|string',       // Gateway untuk network
        'dns_server' => 'required|string',    // DNS Server untuk network
        'netmask' => 'nullable|integer',      // Netmask opsional sebagai integer
    ]);

    // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
    $client = $this->getClient();

    try {
        // Ambil daftar network yang ada untuk melakukan pengecekan
        $query = new Query('/ip/dhcp-server/network/print');
        $networks = $client->query($query)->read();

        // Cek apakah sudah ada network dengan address yang sama
        $existingNetwork = collect($networks)->firstWhere('address', $request->address);

        if ($existingNetwork) {
            // Jika network dengan address yang sama ditemukan, lakukan update
            $updateQuery = new Query('/ip/dhcp-server/network/set');
            $updateQuery->equal('numbers', $existingNetwork['.id'])
                ->equal('gateway', $request->gateway)
                ->equal('dns-server', $request->dns_server);

            // Hanya tambahkan netmask jika ada
            if (!is_null($request->netmask)) {
                $updateQuery->equal('netmask', (int) $request->netmask);
            }

            // Jalankan query update
            $client->query($updateQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'Network updated successfully!'
            ]);
        } else {
            // Jika tidak ditemukan, tambahkan network baru
            $addQuery = (new Query('/ip/dhcp-server/network/add'))
                ->equal('address', $request->address)
                ->equal('gateway', $request->gateway)
                ->equal('dns-server', $request->dns_server);

            // Hanya tambahkan netmask jika ada
            if (!is_null($request->netmask)) {
                $addQuery->equal('netmask', (int) $request->netmask);
            }

            // Jalankan query untuk menambah network baru
            $response = $client->query($addQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'Network added successfully!',
                'response' => $response
            ]);
        }
    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to process network: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function makeLeaseStatic(Request $request)
    {
        // Validasi request dengan tambahan input untuk IP binding
        $request->validate([
            'address' => 'required|ip',  // IP Address dari lease
            'comment' => 'nullable|string',  // Opsional: Komentar untuk lease
            'binding_type' => 'nullable|string|in:blocked,bypassed,regular',  // Tipe IP binding (blocked, bypassed, regular)
            'binding_comment' => 'nullable|string',  // Opsional: Komentar untuk IP binding
        ]);

        $client = $this->getClient();

        try {
            // Ambil daftar lease berdasarkan address
            $query = new Query('/ip/dhcp-server/lease/print');
            $leases = $client->query($query)->read();

            $existingLease = collect($leases)->firstWhere('address', $request->input('address'));

            if (!$existingLease) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lease not found for the given address.'
                ], 404);
            }

            // 1. Hapus lease dynamic terlebih dahulu
            $removeQuery = new Query('/ip/dhcp-server/lease/remove');
            $removeQuery->equal('numbers', $existingLease['.id']);
            $client->query($removeQuery)->read();

            // 2. Tambahkan lease sebagai static lease
            $addStaticQuery = new Query('/ip/dhcp-server/lease/add');
            $addStaticQuery->equal('address', $request->input('address'))
                ->equal('mac-address', $existingLease['mac-address'])
                ->equal('server', $existingLease['server'])
                ->equal('comment', $request->input('comment', ''))
                ->equal('disabled', 'no'); // Pastikan lease diaktifkan

            $client->query($addStaticQuery)->read();

            // 3. Tentukan tipe binding, default ke 'regular' jika tidak diisi
            $bindingType = $request->input('binding_type', 'regular');

            // 4. Tambahkan ke IP binding dengan data yang sama
            $addIpBindingQuery = new Query('/ip/hotspot/ip-binding/add');
            $addIpBindingQuery->equal('mac-address', $existingLease['mac-address'])
                ->equal('address', $request->input('address'))
                ->equal('type', $bindingType)  // blocked, bypassed, atau regular (default)
                ->equal('comment', $request->input('binding_comment', '')); // Komentar opsional

            $client->query($addIpBindingQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'Lease successfully made static and added to IP binding!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to make lease static or add to IP binding: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function getLeases()
    {
        // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
        $client = $this->getClient();

        try {
            // Buat query untuk mengambil data leases dari DHCP server
            $query = new Query('/ip/dhcp-server/lease/print');

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

    public function getDhcpServers()
{
    // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
    $client = $this->getClient();

    try {
        // 1. Ambil data DHCP server
        $dhcpQuery = new Query('/ip/dhcp-server/print');
        $dhcpServers = $client->query($dhcpQuery)->read();

        // 2. Ambil semua interface dari router
        $interfaceQuery = new Query('/interface/print');
        $interfaces = $client->query($interfaceQuery)->read();

        // 3. Dapatkan daftar interface yang digunakan oleh DHCP server
        $usedInterfaces = collect($dhcpServers)->pluck('interface')->all();

        // 4. Filter interface yang belum dipakai oleh DHCP server
        $availableInterfaces = collect($interfaces)->filter(function ($interface) use ($usedInterfaces) {
            return !in_array($interface['name'], $usedInterfaces) && $interface['disabled'] === 'false';
        })->values(); // Reset index untuk hasil yang rapi

        // Kembalikan response ke user
        return response()->json([
            'status' => 'success',
            'dhcpServers' => $dhcpServers,
            'availableInterfaces' => $availableInterfaces
        ]);

    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch data: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function getDhcpServerByName($name)
{
    // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
    $client = $this->getClient();

    try {
        // 1. Ambil data DHCP server berdasarkan nama
        $dhcpQuery = new Query('/ip/dhcp-server/print');
        $dhcpQuery->where('name', $name); // Filter berdasarkan nama
        $dhcpServer = $client->query($dhcpQuery)->read();

        // Cek apakah DHCP server dengan nama tersebut ditemukan
        if (empty($dhcpServer)) {
            return response()->json([
                'status' => 'error',
                'message' => 'DHCP server not found with name: ' . $name,
            ], 404);
        }

        // Kembalikan response dengan detail DHCP server
        return response()->json([
            'status' => 'success',
            'dhcpServer' => $dhcpServer
        ]);

    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch data: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function getNetworks()
    {
        // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
        $client = $this->getClient();

        try {
            // Buat query untuk mengambil data network dari DHCP server
            $query = new Query('/ip/dhcp-server/network/print');

            // Jalankan query
            $networks = $client->query($query)->read();

            // Kembalikan response ke user
            return response()->json([
                'status' => 'success',
                'networks' => $networks
            ]);

        } catch (\Exception $e) {
            // Tangani error jika ada
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch networks: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getNetworksByGateway($gateway)
{
    // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
    $client = $this->getClient();

    try {
        // Buat query untuk mengambil data network dari DHCP server dengan filter berdasarkan gateway
        $query = (new Query('/ip/dhcp-server/network/print'))
                    ->where('gateway', $gateway); // Filter berdasarkan gateway

        // Jalankan query
        $networks = $client->query($query)->read();

        // Kembalikan response ke user
        return response()->json([
            'status' => 'success',
            'networks' => $networks
        ]);

    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch networks by gateway: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function deleteDhcpServerByName($name)
    {
        // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
        $client = $this->getClient();

        try {
            // Pertama, ambil data DHCP server untuk mendapatkan ID server berdasarkan nama
            $query = new Query('/ip/dhcp-server/print');
            $dhcpServers = $client->query($query)->read();

            // Cari DHCP server berdasarkan nama
            $dhcpServer = collect($dhcpServers)->firstWhere('name', $name);

            if (!$dhcpServer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'DHCP server not found with the specified name.',
                ], 404);
            }

            // Buat query untuk menghapus DHCP server berdasarkan ID
            $deleteQuery = new Query('/ip/dhcp-server/remove');
            $deleteQuery->equal('numbers', $dhcpServer['.id']);

            // Jalankan query delete
            $client->query($deleteQuery)->read();

            // Kembalikan response sukses
            return response()->json([
                'status' => 'success',
                'message' => 'DHCP server deleted successfully.',
            ]);

        } catch (\Exception $e) {
            // Tangani error jika ada
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete DHCP server: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteDhcpNetworkByGateway($gateway)
{
    // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
    $client = $this->getClient();

    try {
        // Ambil data network untuk mendapatkan ID berdasarkan gateway
        $query = new Query('/ip/dhcp-server/network/print');
        $dhcpNetworks = $client->query($query)->read();

        // Cari network berdasarkan gateway
        $dhcpNetwork = collect($dhcpNetworks)->firstWhere('gateway', $gateway);

        if (!$dhcpNetwork) {
            return response()->json([
                'status' => 'error',
                'message' => 'DHCP network not found with the specified gateway.',
            ], 404);
        }

        // Buat query untuk menghapus network berdasarkan ID
        $deleteQuery = new Query('/ip/dhcp-server/network/remove');
        $deleteQuery->equal('numbers', $dhcpNetwork['.id']);

        // Jalankan query delete
        $client->query($deleteQuery)->read();

        return response()->json([
            'status' => 'success',
            'message' => "DHCP network with gateway '{$gateway}' deleted successfully.",
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete DHCP network: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function deleteDhcpLeaseAndIpBindingByAddress($address)
    {
        // Panggil fungsi getClient untuk mendapatkan koneksi Mikrotik
        $client = $this->getClient();

        try {
            // Ambil data lease untuk mendapatkan ID berdasarkan address
            $queryLease = new Query('/ip/dhcp-server/lease/print');
            $dhcpLeases = $client->query($queryLease)->read();

            // Cari lease berdasarkan address
            $dhcpLease = collect($dhcpLeases)->firstWhere('address', $address);

            if ($dhcpLease) {
                // Buat query untuk menghapus lease berdasarkan ID
                $deleteLeaseQuery = new Query('/ip/dhcp-server/lease/remove');
                $deleteLeaseQuery->equal('numbers', $dhcpLease['.id']);

                // Jalankan query delete lease
                $client->query($deleteLeaseQuery)->read();
            }

            // Ambil data IP binding untuk mendapatkan ID berdasarkan address
            $queryIpBinding = new Query('/ip/hotspot/ip-binding/print');
            $ipBindings = $client->query($queryIpBinding)->read();

            // Cari IP binding berdasarkan address
            $ipBinding = collect($ipBindings)->firstWhere('address', $address);

            if ($ipBinding) {
                // Buat query untuk menghapus IP binding berdasarkan ID
                $deleteIpBindingQuery = new Query('/ip/hotspot/ip-binding/remove');
                $deleteIpBindingQuery->equal('numbers', $ipBinding['.id']);

                // Jalankan query delete IP binding
                $client->query($deleteIpBindingQuery)->read();
            }

            return response()->json([
                'status' => 'success',
                'message' => "DHCP lease and IP binding with address '{$address}' deleted successfully.",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete DHCP lease or IP binding: ' . $e->getMessage(),
            ], 500);
        }
    }



}
