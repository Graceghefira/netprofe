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
        $client = $this->getClient();

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
            'server_ip' => 'required|ip',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'client_ip_range' => 'required|string',
            'dns_servers' => 'nullable|string',
            'certificate' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Data tidak lengkap atau tidak valid',
                'errors' => $validator->errors()
            ], 500);
        }

        $vpnType = ('openvpn');
        $serverIp = $request->input('server_ip');
        $username = $request->input('username');
        $password = $request->input('password');
        $clientIpRange = $request->input('client_ip_range');
        $dnsServers = $request->input('dns_servers');
        $certificate = $request->input('certificate', 'none');

        if (!$dnsServers) {
            $dnsServers = '8.8.8.8,8.8.4.4';
        }

        if ($vpnType == 'openvpn') {
            $vpnCommands = [
                "/interface ovpn-server server set enabled=yes cipher=aes256 default-profile=default",
                "/interface ovpn-client add name=openvpn-client connect-to={$serverIp} port=1194 protocol=tcp user={$username} password={$password} certificate={$certificate} tls-version=any use-peer-dns=yes add-default-route=yes",
                "/ip pool add name=openvpn-pool ranges={$clientIpRange}",
                "/ppp profile add name=openvpn-profile local-address={$serverIp} remote-address=openvpn-pool dns-server={$dnsServers}",
                "/ppp secret add name={$username} password={$password} profile=openvpn-profile service=ovpn",
                "/ip firewall filter add chain=input action=accept protocol=tcp port=1194 comment=\"Allow OpenVPN\"",
                "/ip firewall nat add chain=srcnat out-interface=ether1 action=masquerade"
            ];
        }
        // Pastikan pemisah baris yang benar
        $plainTextCommands = implode("\n", $vpnCommands);

        // Kembalikan respons dengan opsi teks biasa
        return response($plainTextCommands)
            ->header('Content-Type', 'text/plain');
    }

    public function configureOpenVpnClient(Request $request)
{
    // Validasi inputan dari request
    $validator = Validator::make($request->all(), [
        'server_ip' => 'required|ip',
        'client_ip' => 'required|ip',
        'username' => 'required|string|max:255',
        'password' => 'required|string|max:255',
        'certificate' => 'nullable|string', // Sertifikat untuk OpenVPN (opsional)
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Data tidak lengkap atau tidak valid',
            'errors' => $validator->errors()
        ], 500);
    }

    // Ambil data dari request
    $serverIp = $request->input('server_ip');
    $clientIp = $request->input('client_ip');
    $username = $request->input('username');
    $password = $request->input('password');
    $certificate = $request->input('certificate', 'none');

    // Perintah untuk konfigurasi OpenVPN client di MikroTik kedua
    $vpnClientCommands = [
        "/interface ovpn-client add name=openvpn-client connect-to={$serverIp} port=1194 protocol=tcp user={$username} password={$password} certificate={$certificate} tls-version=any use-peer-dns=yes add-default-route=yes"
    ];

    $plainTextCommands = implode("\n", $vpnClientCommands);

    return response()->json([
        'message' => 'Perintah terminal untuk OpenVPN Client berhasil dibuat',
        'vpn_client_commands' => $vpnClientCommands,
        'plaintext_commands' => $plainTextCommands
    ], 200);
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
