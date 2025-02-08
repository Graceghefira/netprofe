<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function register(Request $request)
{
    // Validasi input
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
        'role' => 'required|in:pegawai,admin,super_admin', // Pastikan role diinputkan
    ]);

    // Membuat pengguna baru
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => $request->role,  // Menyimpan role
    ]);

    // Mengembalikan response sukses
    return response()->json(['message' => 'User registered successfully!', 'user' => $user], 201);
}

public function login(Request $request)
{
    // Validasi input
    $request->validate([
        'email' => 'required|string|email',
        'password' => 'required|string',
    ]);

    // Cek kredensial pengguna
    if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
        $user = Auth::user();

        // Cek role dan arahkan ke halaman yang sesuai
        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Login successful', 'redirect_to' => route('super-admin-dashboard')]);
        } elseif ($user->role === 'admin') {
            return response()->json(['message' => 'Login successful', 'redirect_to' => route('admin-dashboard')]);
        } else {
            return response()->json(['message' => 'Login successful', 'redirect_to' => route('pegawai-dashboard')]);
        }
    }

    return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
}

public function logout(Request $request)
{
    Auth::logout();  // Menghapus sesi pengguna

    $request->session()->invalidate();  // Menghapus semua data sesi
    $request->session()->regenerateToken();  // Menghasilkan token baru untuk mencegah CSRF

    return response()->json(['message' => 'Logout successful'], 200);
}


}
