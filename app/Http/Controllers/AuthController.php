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

class AuthController extends Controller
{
    public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $user = Auth::user();

    $tenant = $user->tenant;

    if (!$tenant) {
        return response()->json(['message' => 'Tenant not found'], 500);
    }

    $encryptedTenantData = [
        'name' => Crypt::encryptString($tenant->id),  // Encrypting tenant name
    ];

    $token = $user->createToken('auth_token')->plainTextToken;

    $cleanToken = explode('|', $token, 2)[1] ?? $token;

    return response()->json([
        'message' => 'Login successful',
        'user' => $user,
        'tenant' => $tenant,
        'tenant_id' =>  $encryptedTenantData,
        'token' => $cleanToken
    ]);
}

    public function register(Request $request,ScriptController $scriptController)
{
    // Validate the request
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:6',
    ]);

    if (User::where('email', $request->email)->exists()) {
        return response()->json([
            'message' => 'Email already exists.',
        ], 500);
    }

    $nameWithUnderscore = Str::slug($request->name, '_');
    $tenantId = "netpro_" . $nameWithUnderscore;

    if (Tenant::where('id', $tenantId)->exists()) {
        return response()->json([
            'message' => 'Tenant ID already exists.',
        ], 500);
    }

    $tenant = Tenant::create([
        'id' => $tenantId,
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'admin',
        'tenant_id' => $tenant->id,
    ]);

    // $scriptController->addScriptAndScheduler(new Request([
    //     'script_name' => 'delete_voucher_script_' . $tenantId,
    //     'scheduler_name' => 'delete_voucher_scheduler_' . $tenantId,
    //     'interval' => '1m', // Set ke 1 menit
    //     'tenant_id' => $tenantId,
    // ]));

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
