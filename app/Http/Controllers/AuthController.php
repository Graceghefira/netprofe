<?php

namespace App\Http\Controllers;
use App\Http\Controllers\ScriptController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
{
    // Validasi input
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required'
    ]);

    // Jika validasi gagal, kembalikan pesan error
    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    // Cek apakah email terdaftar
    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json([
            'message' => 'Email tidak ditemukan'
        ], 404); // HTTP 404 Not Found
    }

    // Cek apakah password benar
    if (!Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Password salah'
        ], 401); // HTTP 401 Unauthorized
    }

    // Autentikasi user
    Auth::login($user);

    // Cek apakah user memiliki tenant
    $tenant = $user->tenant;

    if (!$tenant) {
        return response()->json([
            'message' => 'Tenant not found',
            'errors' => ['tenant' => ['No tenant associated with this user.']]
        ], 500);
    }

    // Enkripsi Tenant ID
    $encryptedTenantData = [
        'name' => Crypt::encryptString($tenant->id),
    ];

    // Generate token
    $token = $user->createToken('auth_token')->plainTextToken;

    // Ambil token tanpa prefix
    $cleanToken = explode('|', $token, 2)[1] ?? $token;

    return response()->json([
        'message' => 'Login successful',
        'user' => $user,
        'tenant' => $tenant,
        'tenant_id' => $encryptedTenantData,
        'token' => $cleanToken
    ], 200);
    }


    public function register(Request $request, ScriptController $scriptController)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        // Jika validasi gagal, kembalikan respons dengan pesan error
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Cek apakah email sudah digunakan
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'message' => 'Email sudah digunakan.'
            ], 409); // HTTP 409 Conflict
        }

        // Generate Tenant ID
        $nameWithUnderscore = Str::slug($request->name, '_');
        $tenantId = "netpro_" . $nameWithUnderscore;

        // Cek apakah tenant ID sudah ada
        if (Tenant::where('id', $tenantId)->exists()) {
            return response()->json([
                'message' => 'Tenant Sudah digunakan.'
            ], 409); // HTTP 409 Conflict
        }

        // Buat Tenant baru
        $tenant = Tenant::create([
            'id' => $tenantId,
        ]);

        // Buat User baru
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }


    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function GetEmail(){
        try {
            // Mengambil semua data users
            $users = User::all();

            return response()->json([
                'success' => true,
                'message' => 'Data users berhasil diambil.',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getUserByToken1(Request $request)
{
    try {
        // Ambil token dari header Authorization
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json(['message' => 'Token tidak ditemukan'], 401);
        }

        // Cari user berdasarkan token
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);

        if (!$personalAccessToken) {
            return response()->json(['message' => 'Token tidak valid'], 401);
        }

        // Dapatkan user terkait dengan token
        $user = $personalAccessToken->tokenable;

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        // Dapatkan data tenant
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant tidak ditemukan'], 500);
        }

        $encryptedTenantData = [
            'name' => Crypt::encryptString($tenant->id),
        ];

        // Return data user dan tenant
        return response()->json([
            'user' => $user,
            'tenant' => $tenant,
            'tenant_id' => $encryptedTenantData
        ]);

    } catch (\Exception $e) {
        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
    }

    public function getUserByToken(Request $request)
{
    try {
        // Ambil token dari header Authorization
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json(['message' => 'Token tidak ditemukan'], 401);
        }

        // Cari user berdasarkan token
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);

        if (!$personalAccessToken) {
            return response()->json(['message' => 'Token tidak valid'], 401);
        }

        // Dapatkan user terkait dengan token dari database central
        $user = $personalAccessToken->tokenable;

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        // Dapatkan data tenant dari database central
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant tidak ditemukan'], 500);
        }

        $encryptedTenantData = [
            'id' => Crypt::encryptString($tenant->id),
        ];

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'tenant' => [
                'id' => $tenant->id,
            ],
            'encrypted_tenant_id' => $encryptedTenantData
        ]);

    } catch (\Exception $e) {
        // Pastikan selalu kembali ke koneksi default jika terjadi error
        // \Config::set('database.default', 'tenant'); // Uncomment jika perlu kembali ke koneksi tenant

        return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
    }
}
}
