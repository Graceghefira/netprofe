<?php

namespace App\Http\Controllers;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotspotProfileController extends Controller
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

    public function setHotspotProfile(Request $request)
{
    // Validasi input
    $request->validate([
        'profile_name' => 'required|string|max:255', // Nama profil
        'shared_users' => 'required|integer|min:1',  // Jumlah shared users
        'rate_limit' => 'nullable|string',           // Batas kecepatan (rx/tx)
        'link' => 'nullable|string',                 // Link untuk disimpan di tabel user_profile_link
    ]);

    // Ambil data dari request
    $profile_name = $request->input('profile_name');
    $shared_users = $request->input('shared_users');
    $rate_limit = $request->input('rate_limit');
    $link = $request->input('link');

    try {
        // Membuat koneksi ke MikroTik
        $client = $this->getClient();

        // Cek apakah profil sudah ada di MikroTik
        $checkQuery = (new Query('/ip/hotspot/user/profile/print'))
            ->where('name', $profile_name);

        $existingProfiles = $client->query($checkQuery)->read();

        if (!empty($existingProfiles)) {
            // Jika profil sudah ada di MikroTik, cek apakah link-nya sudah ada di database
            $existingLink = DB::table('user_profile_link')
                ->where('name', $profile_name)
                ->exists();

            // Jika entri belum ada di database, tambahkan
            if (!$existingLink) {
                DB::table('user_profile_link')->insert([
                    'name' => $profile_name, // Kolom 'name' menerima 'profile_name'
                    'link' => $link,         // Kolom 'link' menerima 'link'
                    'created_at' => now(),   // Timestamp saat ini untuk created_at
                    'updated_at' => now(),   // Timestamp saat ini untuk updated_at
                ]);

                // Panggil fungsi getHotspotUsers1 setelah update
                $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
                $hotspotController->getHotspotProfile();

                return response()->json([
                    'message' => 'Profile sudah ada, tapi link-nya belum ada. Saya tambahin dulu ya'
                ], 200);
            }

            // Jika profil dan link sudah ada, tidak ada perubahan yang dilakukan
            $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            $hotspotController->getHotspotUsers1();

            return response()->json(['message' => 'Profile dan link sudah ada, tidak ada perubahan yang dilakukan'], 200);
        } else {
            // Jika profil belum ada, tambahkan profil baru ke MikroTik
            $addQuery = (new Query('/ip/hotspot/user/profile/add'))
                ->equal('name', $profile_name)
                ->equal('shared-users', $shared_users)
                ->equal('keepalive-timeout', 'none'); // Set Keepalive Timeout menjadi unlimited (none)

            // Hanya masukkan rate-limit jika tidak kosong
            if (!empty($rate_limit)) {
                $addQuery->equal('rate-limit', $rate_limit);
            }

            $client->query($addQuery)->read();

            // Tambahkan entri baru ke tabel user_profile_link
            DB::table('user_profile_link')->insert([
                'name' => $profile_name, // Kolom 'name' menerima 'profile_name'
                'link' => $link,         // Kolom 'link' menerima 'link'
                'created_at' => now(),    // Timestamp saat ini untuk created_at
                'updated_at' => now(),    // Timestamp saat ini untuk updated_at
            ]);

            // Panggil fungsi getHotspotUsers1 setelah profil berhasil ditambahkan
            $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            $hotspotController->getHotspotProfile();

            return response()->json(['message' => 'Hotspot profile created successfully'], 201);
        }
    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotProfile(Request $request)
{
    try {
        // Koneksi ke MikroTik
        $client = $this->getClient();

        // Query untuk mendapatkan semua profil Hotspot
        $query = new Query('/ip/hotspot/user/profile/print');

        // Eksekusi query
        $profiles = $client->query($query)->read();

        // Jika profil ditemukan, kita ambil informasi Shared Users dan Rate Limit
        if (!empty($profiles)) {
            $result = [];

            // Loop melalui setiap profil dan ambil data penting
            foreach ($profiles as $profile) {
                $result[] = [
                    'profile_name' => $profile['name'],
                    'shared_users' => $profile['shared-users'] ?? 'Not set',
                    'rate_limit' => $profile['rate-limit'] ?? 'Not set',
                ];
            }

            // Kembalikan hasil sebagai response JSON tanpa pagination
            return response()->json(['profiles' => $result], 200);
        } else {
            // Jika tidak ada profil ditemukan
            return response()->json(['message' => 'No profiles found'], 404);
        }
    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotProfilePagi(Request $request)
    {
        try {
            // Koneksi ke MikroTik
            $client = $this->getClient();

            // Query untuk mendapatkan semua profil Hotspot
            $query = new Query('/ip/hotspot/user/profile/print');

            // Eksekusi query
            $profiles = $client->query($query)->read();

            // Jika profil ditemukan, kita ambil informasi Shared Users, Rate Limit, dan Link
            if (!empty($profiles)) {
                $result = [];

                // Loop melalui setiap profil dan ambil data penting
                foreach ($profiles as $profile) {
                    // Mengambil link dari tabel user_profile_link menggunakan DB facade
                    $link = DB::table('user_profile_link')
                        ->where('name', $profile['name'])
                        ->value('link') ?? 'No link available';

                    $result[] = [
                        'profile_name' => $profile['name'],
                        'shared_users' => $profile['shared-users'] ?? 'Not set',
                        'rate_limit' => $profile['rate-limit'] ?? 'Not set',
                        'link' => $link, // Tambahkan link ke hasil
                    ];
                }

                // Pagination
                $page = $request->input('page', 1); // Mendapatkan nomor halaman dari request, default halaman 1
                $perPage = 5; // Jumlah data per halaman

                // Menggunakan array_chunk untuk membagi array menjadi bagian-bagian kecil
                $chunkedProfiles = array_chunk($result, $perPage);

                // Mengecek apakah halaman yang diminta valid
                if ($page > count($chunkedProfiles) || $page < 1) {
                    return response()->json(['message' => 'Page not found'], 404);
                }

                // Ambil data berdasarkan halaman yang diminta
                $paginatedResult = $chunkedProfiles[$page - 1];

                // Menambahkan informasi pagination
                $paginationData = [
                    'current_page' => $page,
                    'total_pages' => count($chunkedProfiles),
                    'total_profiles' => count($result),
                    'profiles' => $paginatedResult,
                ];

                // Kembalikan hasil sebagai response JSON
                return response()->json($paginationData, 200);
            } else {
                // Jika tidak ada profil ditemukan
                return response()->json(['message' => 'No profiles found'], 404);
            }
        } catch (\Exception $e) {
            // Tangani error jika ada
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getHotspotProfileByName(Request $request, $profileName)
    {
        try {
            // Koneksi ke MikroTik
            $client = $this->getClient();

            // Query untuk mendapatkan profil Hotspot berdasarkan nama
            $query = new Query('/ip/hotspot/user/profile/print');
            $query->where('name', $profileName);

            // Eksekusi query
            $profiles = $client->query($query)->read();

            // Jika profil ditemukan, kita ambil informasi Shared Users, Rate Limit, dan Link dari database
            if (!empty($profiles)) {
                $profile = $profiles[0]; // Karena profil yang dicari hanya satu

                // Query ke database untuk mendapatkan link dari tabel user_profile_link
                $link = DB::table('user_profile_link')
                    ->where('name', $profileName)
                    ->value('link');

                // Buat hasil yang akan dikembalikan dalam format JSON
                $result = [
                    'profile_name' => $profile['name'],
                    'shared_users' => $profile['shared-users'] ?? 'Not set', // Mendapatkan shared-users dari MikroTik
                    'rate_limit' => $profile['rate-limit'] ?? 'Not set',    // Mendapatkan rate-limit dari MikroTik
                    'link' => $link ?? 'No link found',                      // Link dari database, dengan default jika tidak ada
                ];

                // Kembalikan hasil sebagai response JSON
                return response()->json($result, 200);
            } else {
                // Jika profil tidak ditemukan
                return response()->json(['message' => 'Profile not found'], 404);
            }
        } catch (\Exception $e) {
            // Tangani error jika ada
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteHotspotProfile($profile_name)
{
    try {
        // Koneksi ke MikroTik
        $client = $this->getClient();

        // Query untuk mencari profil berdasarkan nama
        $checkQuery = (new Query('/ip/hotspot/user/profile/print'))
            ->where('name', $profile_name);

        // Eksekusi query untuk mencari profil
        $profiles = $client->query($checkQuery)->read();

        // Jika profil ditemukan
        if (!empty($profiles)) {
            $profile_id = $profiles[0]['.id']; // Ambil ID profil

            // Query untuk menghapus profil berdasarkan ID
            $deleteQuery = (new Query('/ip/hotspot/user/profile/remove'))
                ->equal('.id', $profile_id);

            // Eksekusi query untuk menghapus profil
            $client->query($deleteQuery)->read();

            // Panggil fungsi getHotspotUsers1 dari MqttController
            $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            $hotspotController->getHotspotProfile();

            // Kembalikan pesan sukses
            return response()->json(['message' => 'Hotspot profile deleted successfully'], 200);
        } else {
            // Jika profil tidak ditemukan
            return response()->json(['message' => 'Profile not found'], 404);
        }
    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function updateHotspotProfile(Request $request, $profile_name)
{
    // Validasi input
    $request->validate([
        'link' => 'required|url', // Link baru yang akan diupdate
        'shared_users' => 'nullable|integer', // Jumlah shared users, opsional
        'rate_limit' => 'nullable|string', // Rate limit, opsional
    ]);

    // Ambil input dari request
    $link = $request->input('link');
    $shared_users = $request->input('shared_users');
    $rate_limit = $request->input('rate_limit');

    try {
        // Koneksi ke MikroTik
        $client = $this->getClient();

        // Query untuk mencari profil berdasarkan nama
        $checkQuery = (new Query('/ip/hotspot/user/profile/print'))
            ->where('name', $profile_name);

        // Eksekusi query untuk mencari profil
        $profiles = $client->query($checkQuery)->read();

        // Jika profil ditemukan
        if (!empty($profiles)) {
            $profile_id = $profiles[0]['.id']; // Ambil ID profil

            // Query untuk mengedit profil berdasarkan ID
            $updateQuery = (new Query('/ip/hotspot/user/profile/set'))
                ->equal('.id', $profile_id);

            // Tambahkan field yang diupdate jika ada
            if ($shared_users) {
                $updateQuery->equal('shared-users', $shared_users);
            }
            if ($rate_limit) {
                $updateQuery->equal('rate-limit', $rate_limit);
            }

            // Eksekusi query untuk mengedit profil
            $client->query($updateQuery)->read();

            // Periksa apakah profil ada di database user_profile_link
            $existingProfile = DB::table('user_profile_link')
                ->where('name', $profile_name)
                ->first();

            if ($existingProfile) {
                // Update link jika profil ditemukan
                DB::table('user_profile_link')
                    ->where('name', $profile_name)
                    ->update(['link' => $link]);
            } else {
                // Insert data baru jika profil tidak ditemukan
                DB::table('user_profile_link')
                    ->insert([
                        'name' => $profile_name,
                        'link' => $link,
                    ]);
            }

            // Panggil fungsi getHotspotProfile dari MqttController
            $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            $hotspotController->getHotspotProfile();

            // Kembalikan pesan sukses
            return response()->json(['message' => 'Hotspot profile and link updated successfully'], 200);
        } else {
            // Jika profil tidak ditemukan di MikroTik
            return response()->json(['message' => 'Profile not found'], 404);
        }
    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }



}
