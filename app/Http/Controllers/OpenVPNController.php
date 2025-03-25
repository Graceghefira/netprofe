<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RouterOS\Client;
use RouterOS\Query;

class OpenVPNController extends CentralController
{

    public function createOpenVpnClient1(Request $request)
{
    // Validasi inputan dari request
    $validator = Validator::make($request->all(), [
        'client_name' => 'required|string|max:255',
        'server_ip' => 'required|ip',
        'port' => 'required|string',
        'username' => 'required|string',
        'password' => 'required|string',
        'certificate' => 'nullable|string',
        'cipher' => 'nullable|string',
        'auth' => 'nullable|string',
        'user_name' => 'required|string|max:255',
        'user_password' => 'required|string|max:255',
    ]);

    // Jika validasi gagal, kembalikan response 500
    if ($validator->fails()) {
        return response()->json([
            'message' => 'Data tidak lengkap atau tidak valid',
            'errors' => $validator->errors()
        ], 500);
    }

    // Ambil data dari request
    $clientName = $request->input('client_name');
    $serverIp = $request->input('server_ip');
    $port = $request->input('port');
    $username = $request->input('username');
    $password = $request->input('password');
    $certificate = $request->input('certificate', 'none');
    $cipher = $request->input('cipher', 'blowfish128');
    $auth = $request->input('auth', 'sha256');
    $userName = $request->input('user_name');
    $userPassword = $request->input('user_password');

    // Membuat perintah terminal MikroTik untuk OpenVPN Client
    $openvpnCommand = "/interface/ovpn-client/add " .
        "name={$clientName} " .
        "connect-to={$serverIp} " .
        "port={$port} " .
        "protocol=tcp " .
        "user={$username} " .
        "password={$password} " .
        "cipher={$cipher} " .
        "auth={$auth} " .
        "certificate={$certificate} " .
        "tls-version=any " .
        "use-peer-dns=yes " .
        "add-default-route=yes";

    // Membuat perintah terminal MikroTik untuk menambahkan user
    $addUserCommand = "/user/add name={$userName} password={$userPassword} group=full allowed-address=0.0.0.0/0";

    // Mengembalikan perintah terminal sebagai output
    return response()->json([
        'message' => 'Perintah terminal untuk OpenVPN Client dan user berhasil dibuat',
        'terminal_commands' => [
            'openvpn_command' => $openvpnCommand,
            'add_user_command' => $addUserCommand
        ]
    ], 200);
    }

    public function checkInterface(Request $request)
{
    $request->validate([
        'interface_name' => 'required|string'
    ]);

    try {
        $client = $this->getClientLogin();

        $query = new Query('/interface/print');
        $query->where('name', $request->interface_name);

        $interfaces = $client->query($query)->read();

        if (count($interfaces) > 0) {
            return response()->json([
                'exists' => true,
                'details' => "VPN Sudah Ada"
            ]);
        } else {
            return response()->json([
                'exists' => false,
                'details' => "VPN Belum Ada"
            ]);
        }

    } catch (\Exception $e) {
        Log::error('Gagal memeriksa interface: ' . $e->getMessage());

        return response()->json([
            'error' => 'Gagal terhubung ke Mikrotik',
            'message' => $e->getMessage()
        ], 500);
    }
    }


    public function configureVpnServer(Request $request)
{
    $validator = Validator::make($request->all(), [
        'username' => 'required|string|max:255',
        'password' => 'required|string|max:255',
        'pool_name' => 'required|string',
        'client_ip_range' => 'required|string',
        'port_Nat' => 'required|string',
        'address_network' => 'required|string',
        'port_address' => 'required|string'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Data tidak lengkap atau tidak valid',
            'errors' => $validator->errors()
        ], 400);
    }

    $serverIp = '45.149.93.122';
    $username = $request->input('username');
    $password = $request->input('password');
    $natport = $request->input('port_Nat');
    $clientIpRange = $request->input('client_ip_range');
    $addressNetwork = $request->input('address_network');
    $portAddress = $request->input('port_address');
    $certificate = 'none';
    $ovpnInterface = "ovpn-{$username}";
    $poolName = "{$username}-pool";
    $profileName = "{$username}-profile";
    $clientName = "client-{$username}";

    // **Menambahkan 1 ke last octet dari IP pool**
    $ipParts = explode('.', $clientIpRange); // Pisahkan IP
    if (count($ipParts) == 4) {
        $ipParts[3] = (int) $ipParts[3] + 1; // Tambah 1 ke angka terakhir
        if ($ipParts[3] > 254) {
            $ipParts[3] = 2; // Hindari overflow
        }
        $localAddress = implode('.', $ipParts); // Gabungkan kembali
    } else {
        $localAddress = $clientIpRange; // Jika error, gunakan IP asli
    }

    $vpnCommands = [
        "/ip pool add name={$poolName} ranges={$clientIpRange}",
        "/ppp profile add name={$profileName} local-address={$localAddress} remote-address={$poolName}",
        "/ppp secret add name={$username} password={$password} profile={$profileName} service=ovpn",
        "/ip firewall nat add chain=dstnat protocol=tcp dst-address={$serverIp} dst-port={$natport} action=dst-nat to-addresses={$addressNetwork} to-ports={$portAddress} comment=Forward_{$username}",
        "/interface ovpn-client add name={$clientName} connect-to={$serverIp} port=1194 protocol=tcp user={$username} password={$password} certificate={$certificate} auth=sha1 cipher=aes256-cbc tls-version=any use-peer-dns=yes",
        "/ip firewall nat add chain=srcnat out-interface=<ovpn-{$username}> action=masquerade comment=Masquerade_{$username}",
    ];

    return response()->json([
        'message' => 'VPN configuration generated successfully',
        'commands' => $vpnCommands
    ]);
    }

    public function configureVpnServer1(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'pool_name' => 'required|string',
            'client_ip_range' => 'required|string',
            'port_Nat' => 'required|string',
            'address_network' => 'required|string',
            'port_address' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak lengkap atau tidak valid',
                'errors' => $validator->errors()
            ], 400);
        }

        // Dapatkan instance client Mikrotik
        $client = $this->getClient();
        if (!$client) {
            return response()->json([
                'message' => 'Koneksi ke Mikrotik gagal',
            ], 500);
        }

        $serverIp = '45.149.93.122';
        $username = $request->input('username');
        $password = $request->input('password');
        $natport = $request->input('port_Nat');
        $clientIpRange = $request->input('client_ip_range');
        $addressNetwork = $request->input('address_network');
        $portAddress = $request->input('port_address');
        $certificate = 'none';
        $ovpnInterface = "ovpn-{$username}";
        $poolName = "{$username}-pool";
        $profileName = "{$username}-profile";
        $clientName = "client-{$username}";

        // **Menambahkan 1 ke last octet dari IP pool**
        $ipParts = explode('.', $clientIpRange);
        if (count($ipParts) == 4) {
            $ipParts[3] = (int) $ipParts[3] + 1;
            if ($ipParts[3] > 254) {
                $ipParts[3] = 2;
            }
            $localAddress = implode('.', $ipParts);
        } else {
            $localAddress = $clientIpRange;
        }

        try {
            // **Eksekusi perintah langsung ke Mikrotik**
            $client->query(
                (new Query('/ip/pool/add'))
                    ->equal('name', $poolName)
                    ->equal('ranges', $clientIpRange)
            )->read();

            $client->query(
                (new Query('/ppp/profile/add'))
                    ->equal('name', $profileName)
                    ->equal('local-address', $localAddress)
                    ->equal('remote-address', $poolName)
            )->read();

            $client->query(
                (new Query('/ppp/secret/add'))
                    ->equal('name', $username)
                    ->equal('password', $password)
                    ->equal('profile', $profileName)
                    ->equal('service', 'ovpn')
            )->read();

            $client->query(
                (new Query('/ip/firewall/nat/add'))
                    ->equal('chain', 'dstnat')
                    ->equal('protocol', 'tcp')
                    ->equal('dst-address', $serverIp)
                    ->equal('dst-port', $natport)
                    ->equal('action', 'dst-nat')
                    ->equal('to-addresses', $addressNetwork)
                    ->equal('to-ports', $portAddress)
                    ->equal('comment', "Forward_{$username}")
            )->read();

            // **Perintah untuk OpenVPN Client & Masquerade hanya ditampilkan sebagai output**
            $vpnCommands = [
                "/interface ovpn-client add name={$clientName} connect-to={$serverIp} port=1194 protocol=tcp user={$username} password={$password} certificate={$certificate} auth=sha1 cipher=aes256-cbc tls-version=any use-peer-dns=yes",
                "/ip firewall nat add chain=srcnat out-interface=<{$ovpnInterface}> action=masquerade comment=Masquerade_{$username}",
            ];

            return response()->json([
                'message' => 'VPN berhasil dikonfigurasi di Mikrotik, tetapi Masquerade dan OpenVPN Client perlu dijalankan manual',
                'commands' => $vpnCommands
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengkonfigurasi VPN',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function checkVpnStatus()
{
    $client = new Client([
        'host' => '192.168.88.1', // Ganti dengan IP MikroTik
        'user' => 'admin',
        'pass' => 'password',
        'port' => 8728, // API port MikroTik
    ]);

    $query = new Query('/interface ovpn-server server print');
    $response = $client->query($query)->read();

    return response()->json($response);
    }

}
