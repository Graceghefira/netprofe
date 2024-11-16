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
use App\Providers\RadiusService;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected $radiusService;

    public function __construct(RadiusService $radiusService)
    {
        $this->radiusService = $radiusService;
    }

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
    
    public function loginWithMikrotikUser(Request $request)
    {
        try {
            $client = $this->getClient();

            // Ambil input username dan password dari request
            $username = $request->input('username');
            $password = $request->input('password');

            // Query untuk memeriksa pengguna di MikroTik berdasarkan username
            $getUserQuery = (new Query('/ip/hotspot/user/print'))->where('name', $username);
            $users = $client->query($getUserQuery)->read();

            // Jika pengguna tidak ditemukan di MikroTik
            if (empty($users)) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Ambil user yang ditemukan
            $user = $users[0]; // Asumsi hanya ada satu user yang cocok dengan name

            // Periksa password
            if (isset($user['password']) && $user['password'] === $password) {
                // Login berhasil, buat token atau sesi
                $token = base64_encode(Str::random(40)); // Menggunakan Str::random untuk token

                // Simpan sesi atau token
                session(['user' => $username, 'token' => $token]);

                return response()->json([
                    'message' => 'Login successful',
                    'username' => $username,
                    'token' => $token,
                ]);
            }

            // Jika password tidak cocok
            return response()->json(['message' => 'Invalid password'], 401);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


}
