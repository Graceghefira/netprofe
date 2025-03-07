<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RouterOS\Client;
use RouterOS\Query;

class OpenVPNController extends CentralController
{
    public function createOpenVpnClient(Request $request)
    {
        // Validasi inputan dari request
        $request->validate([
            'client_name' => 'required|string|max:255',
            'server_ip' => 'required|ip',
            'username' => 'required|string',
            'password' => 'required|string',
            'certificate' => 'nullable|string',
            'cipher' => 'nullable|string',
            'auth' => 'nullable|string',
        ]);

        // Ambil data dari request
        $clientName = $request->input('client_name');
        $serverIp = $request->input('server_ip');
        $username = $request->input('username');
        $password = $request->input('password');
        $certificate = $request->input('certificate', 'none');
        $cipher = $request->input('cipher', 'blowfish128');
        $auth = $request->input('auth', 'sha256');

        try {
            // Dapatkan koneksi ke MikroTik
            $client = $this->getClient();

            // Membuat query OpenVPN Client
            $query = new Query('/interface/ovpn-client/add');
            $query->equal('name', $clientName);
            $query->equal('connect-to', $serverIp);
            $query->equal('port', '1194');
            $query->equal('protocol', 'tcp');
            $query->equal('user', $username);
            $query->equal('password', $password);
            $query->equal('cipher', $cipher);
            $query->equal('auth', $auth);
            $query->equal('certificate', $certificate);
            $query->equal('tls-version', 'any');
            $query->equal('use-peer-dns', 'yes');
            $query->equal('add-default-route', 'yes');

            // Kirim query ke MikroTik dan baca response
            $response = $client->query($query)->read();

            // Membuat perintah terminal MikroTik berdasarkan response
            $command = "/interface/ovpn-client/add " .
                "name=\"{$clientName}\" " .
                "connect-to=\"{$serverIp}\" " .
                "port=1194 " .
                "protocol=tcp " .
                "user=\"{$username}\" " .
                "password=\"{$password}\" " .
                "cipher=\"{$cipher}\" " .
                "auth=\"{$auth}\" " .
                "certificate=\"{$certificate}\" " .
                "tls-version=\"any\" " .
                "use-peer-dns=yes " .
                "add-default-route=yes";

            return response()->json([
                'message' => 'OpenVPN Client berhasil dibuat',
                'terminal_command' => $command, // Output perintah terminal MikroTik
                'data' => $response
            ], 200);

        } catch (\Exception $e) {
            // Tangani error jika koneksi atau query gagal
            return response()->json([
                'message' => 'Gagal membuat OpenVPN Client: ' . $e->getMessage()
            ], 400);
        }
    }

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

            return response()->json([
                'exists' => count($interfaces) > 0,
                'details' => "Vpn Sudah Ada"
            ]);

        } catch (\Exception $e) {
            Log::error('Gagal memeriksa interface: ' . $e->getMessage());

            return response()->json([
                'error' => 'Gagal terhubung ke Mikrotik',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
