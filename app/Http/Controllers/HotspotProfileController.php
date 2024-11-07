<?php

namespace App\Http\Controllers;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;

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
        ]);

        // Ambil data dari request
        $profile_name = $request->input('profile_name');
        $shared_users = $request->input('shared_users');
        $rate_limit = $request->input('rate_limit');

        try {
            // Membuat koneksi ke MikroTik
            $client = $this->getClient();

            // Cek apakah profil sudah ada
            $checkQuery = (new Query('/ip/hotspot/user/profile/print'))
                ->where('name', $profile_name);

            $existingProfiles = $client->query($checkQuery)->read();

            if (!empty($existingProfiles)) {
                // Profil sudah ada, update data
                $updateQuery = (new Query('/ip/hotspot/user/profile/set'))
                    ->equal('.id', $existingProfiles[0]['.id']) // Update berdasarkan ID profil
                    ->equal('name', $profile_name)
                    ->equal('shared-users', $shared_users);

                // Hanya masukkan rate-limit jika tidak kosong
                if (!empty($rate_limit)) {
                    $updateQuery->equal('rate-limit', $rate_limit);
                }

                $client->query($updateQuery)->read();

                return response()->json(['message' => 'Hotspot profile updated successfully'], 200);
            } else {
                // Jika profil belum ada, tambahkan profil baru
                $addQuery = (new Query('/ip/hotspot/user/profile/add'))
                    ->equal('name', $profile_name)
                    ->equal('shared-users', $shared_users)
                    ->equal('keepalive-timeout', 'none'); // Set Keepalive Timeout menjadi unlimited (none)

                // Hanya masukkan rate-limit jika tidak kosong
                if (!empty($rate_limit)) {
                    $addQuery->equal('rate-limit', $rate_limit);
                }

                $client->query($addQuery)->read();

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

            // Jika profil ditemukan, kita ambil informasi Shared Users dan Rate Limit
            if (!empty($profiles)) {
                $profile = $profiles[0]; // Karena profil yang dicari hanya satu

                $result = [
                    'profile_name' => $profile['name'],
                    'shared_users' => $profile['shared-users'] ?? 'Not set',
                    'rate_limit' => $profile['rate-limit'] ?? 'Not set',
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

    public function editHotspotProfile(Request $request, $profile_name)
    {
        // Validasi input yang masuk
        $request->validate([
            'shared_users' => 'sometimes|integer|min:1', // Nilai shared_users harus integer minimal 1
            'rate_limit' => 'sometimes|nullable|string', // Format rate limit, misalnya "10M/10M"
        ]);

        try {
            // Koneksi ke MikroTik
            $client = $this->getClient();

            // Query untuk mendapatkan profil hotspot berdasarkan nama profil
            $query = new Query('/ip/hotspot/user/profile/print');
            $profiles = $client->query($query)->read();

            // Cek apakah profil ditemukan
            $foundProfile = null;
            foreach ($profiles as $profile) {
                if ($profile['name'] === $profile_name) {
                    $foundProfile = $profile;
                    break;
                }
            }

            if (!$foundProfile) {
                // Jika profil tidak ditemukan, kembalikan pesan 404
                return response()->json(['message' => 'Profile tidak ditemukan.'], 404);
            }

            // Siapkan query untuk mengupdate profil berdasarkan .id dari profil yang ditemukan
            $updateQuery = (new Query('/ip/hotspot/user/profile/set'))
                ->equal('.id', $foundProfile['.id']);

            // Update shared_users jika ada dalam request
            if ($request->has('shared_users')) {
                $updateQuery->equal('shared-users', $request->input('shared_users'));
            }

            // Update rate_limit jika ada dalam request
            if ($request->has('rate_limit')) {
                $updateQuery->equal('rate-limit', $request->input('rate_limit'));
            }

            // Eksekusi query untuk mengupdate profil
            $client->query($updateQuery)->read();

            // Kembalikan pesan sukses
            return response()->json(['message' => 'Profile berhasil diperbarui.'], 200);

        } catch (\Exception $e) {
            // Tangani error jika terjadi
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

    public function updateHotspotProfile(Request $request)
        {
            // Validasi input
            $request->validate([
                'profile_name' => 'required|string', // Nama profil yang ingin diedit
                'new_profile_name' => 'nullable|string', // Nama profil baru, opsional
                'shared_users' => 'nullable|integer', // Jumlah shared users, opsional
                'rate_limit' => 'nullable|string', // Rate limit, opsional
            ]);

            $profile_name = $request->input('profile_name');
            $new_profile_name = $request->input('new_profile_name');
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
                    if ($new_profile_name) {
                        $updateQuery->equal('name', $new_profile_name);
                    }
                    if ($shared_users) {
                        $updateQuery->equal('shared-users', $shared_users);
                    }
                    if ($rate_limit) {
                        $updateQuery->equal('rate-limit', $rate_limit);
                    }

                    // Eksekusi query untuk mengedit profil
                    $client->query($updateQuery)->read();

                    // Kembalikan pesan sukses
                    return response()->json(['message' => 'Hotspot profile updated successfully'], 200);
                } else {
                    // Jika profil tidak ditemukan
                    return response()->json(['message' => 'Profile not found'], 404);
                }
            } catch (\Exception $e) {
                // Tangani error jika ada
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }
}
