<?php


namespace App\Http\Controllers;

use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;
use App\Models\AkunKantor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ByteController extends BaseMikrotikController
{

    public function getHotspotUsers()
{
    try {
        // Membuat koneksi ke MikroTik
        $endpoint = Cache::get('global_endpoint');

         // Dapatkan client berdasarkan endpoint
         $client = $this->getClient($endpoint);

        // Query untuk mendapatkan daftar semua pengguna hotspot
        $userQuery = new Query('/ip/hotspot/user/print');
        $users = $client->query($userQuery)->read();

        // Query untuk mendapatkan pengguna yang sedang aktif
        $activeQuery = new Query('/ip/hotspot/active/print');
        $activeUsers = $client->query($activeQuery)->read();

        $totalBytesIn = session()->get('total_bytes_in', 0);
        $totalBytesOut = session()->get('total_bytes_out', 0);

        $activeUsersMap = [];
        foreach ($activeUsers as $activeUser) {
            $username = $activeUser['user'];
            $activeUsersMap[$username] = $activeUser;
        }

        $modifiedUsers = array_map(function ($user) use (&$totalBytesIn, &$totalBytesOut, $activeUsersMap) {
            $newUser = [];
            foreach ($user as $key => $value) {
                $newKey = str_replace('.id', 'id', $key);
                $newUser[$newKey] = $value;
            }

            if (isset($activeUsersMap[$user['name']])) {
                $activeUser = $activeUsersMap[$user['name']];
                $newUser['bytes-in'] = isset($activeUser['bytes-in']) ? (int)$activeUser['bytes-in'] : 0;
                $newUser['bytes-out'] = isset($activeUser['bytes-out']) ? (int)$activeUser['bytes-out'] : 0;
            } else {
                $existingUser = DB::table('user_bytes_log')
                    ->where('user_name', $user['name'])
                    ->orderBy('timestamp', 'desc')
                    ->first();

                if ($existingUser) {
                    $newUser['bytes-in'] = (int)$existingUser->bytes_in;
                    $newUser['bytes-out'] = (int)$existingUser->bytes_out;
                } else {
                    $newUser['bytes-in'] = 0;
                    $newUser['bytes-out'] = 0;
                }
            }

            $totalBytesIn += isset($newUser['bytes-in']) ? (int)$newUser['bytes-in'] : 0;
            $totalBytesOut += isset($newUser['bytes-out']) ? (int)$newUser['bytes-out'] : 0;

            return $newUser;
        }, $users);

        $totalBytes = $totalBytesIn + $totalBytesOut;

        session()->put('total_bytes_in', $totalBytesIn);
        session()->put('total_bytes_out', $totalBytesOut);

        return response()->json([
            'total_user' => count($modifiedUsers),
            'users' => $modifiedUsers,
            'total_bytes_in' => $totalBytesIn,
            'total_bytes_out' => $totalBytesOut,
            'total_bytes' => $totalBytes,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function deleteHotspotUserByPhoneNumber($no_hp)
{
    try {
        // Membuat koneksi ke MikroTik
        $endpoint = Cache::get('global_endpoint');

         // Dapatkan client berdasarkan endpoint
         $client = $this->getClient($endpoint);

        // Query untuk mendapatkan pengguna berdasarkan nomor telepon
        $query = new Query('/ip/hotspot/user/print');
        $query->where('name', $no_hp); // 'name' adalah field untuk username di MikroTik

        $users = $client->query($query)->read();

        if (empty($users)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Ambil pengguna pertama (jika ada banyak)
        $user = $users[0];

        // Step 1: Disconnect any active sessions associated with the user
        $activeSessionsQuery = (new Query('/ip/hotspot/active/print'))
            ->where('user', $user['name']); // Filter by the username

        $activeSessions = $client->query($activeSessionsQuery)->read();

        // Loop through active sessions and remove them
        foreach ($activeSessions as $session) {
            $terminateSessionQuery = (new Query('/ip/hotspot/active/remove'))
                ->equal('.id', $session['.id']); // Terminate the session

            $client->query($terminateSessionQuery)->read();
        }

        // Step 2: Delete the hotspot user
        $deleteQuery = (new Query('/ip/hotspot/user/remove'))->equal('.id', $user['.id']);
        $client->query($deleteQuery)->read();

        // Step 3: Check if profile_name is staff or Owner and delete from database if true
        if (in_array($user['profile'], ['Owner', 'staff'])) {
            AkunKantor::where('no_hp', $no_hp)->delete();
        }

        // Step 4: Call getHotspotUsers1 from another controller
        $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
        $hotspotController->getHotspotUsers1();

        return response()->json(['message' => 'Hotspot user deleted successfully']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotProfile(Request $request)
{
    try {
        // Koneksi ke MikroTik
        // Membuat koneksi ke MikroTik
        $endpoint = Cache::get('global_endpoint');

         // Dapatkan client berdasarkan endpoint
         $client = $this->getClient($endpoint);

        // Query untuk mendapatkan semua profil Hotspot
        $profileQuery = new Query('/ip/hotspot/user/profile/print');
        $profiles = $client->query($profileQuery)->read();

        // Query untuk mendapatkan semua pengguna hotspot
        $userQuery = new Query('/ip/hotspot/user/print');
        $users = $client->query($userQuery)->read();

        // Jika profil ditemukan, kita ambil informasi Shared Users dan Rate Limit
        if (!empty($profiles)) {
            $result = [];

            // Buat array untuk mengelompokkan pengguna berdasarkan profil
            $usersByProfile = [];

            // Loop melalui setiap user dan kelompokkan berdasarkan profile name
            foreach ($users as $user) {
                $profileName = $user['profile'] ?? 'Unknown';
                $usersByProfile[$profileName][] = [
                    'name' => $user['name'],
                    'bytes-in' => $user['bytes-in'] ?? '0',
                    'bytes-out' => $user['bytes-out'] ?? '0',
                    'uptime' => $user['uptime'] ?? 'Not available'
                ];
            }

            // Loop melalui setiap profil dan ambil data penting
            foreach ($profiles as $profile) {
                $profileName = $profile['name'];
                $result[] = [
                    'profile_name' => $profileName,
                    'shared_users' => $profile['shared-users'] ?? 'Not set',
                    'rate_limit' => $profile['rate-limit'] ?? 'Not set',
                    'users' => $usersByProfile[$profileName] ?? [] // Ambil pengguna yang terkait dengan profil ini
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

    public function getHotspotUsersByDateRangeWithLoginCheck(Request $request)
{
    try {
        // Mendapatkan nilai startDate dan endDate dari request body
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        // Validasi jika tanggal tidak diberikan
        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Tanggal awal dan akhir harus disediakan'], 400);
        }

        // Ubah format tanggal agar mencakup seluruh hari
        $startDate = $startDate . ' 00:00:00'; // Mulai dari awal hari
        $endDate = $endDate . ' 23:59:59';     // Hingga akhir hari

        // Query ke database untuk mendapatkan pengguna yang login dalam rentang tanggal yang diberikan
        $usersQuery = DB::table('user_bytes_log')
            ->select(
                'user_name',
                'role',
                DB::raw('SUM(bytes_in) as total_bytes_in'),
                DB::raw('SUM(bytes_out) as total_bytes_out'),
                DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_user_bytes')
            )
            ->whereBetween('timestamp', [$startDate, $endDate]) // Filter berdasarkan rentang tanggal
            ->groupBy('user_name', 'role') // Mengelompokkan berdasarkan nama pengguna dan role
            ->orderBy(DB::raw('SUM(bytes_in) + SUM(bytes_out)'), 'desc'); // Mengurutkan berdasarkan total byte yang digunakan

        // Pagination dengan limit 5
        $paginatedUsers = $usersQuery->paginate(5);

        // Mengambil data pengguna dari hasil pagination
        $users = $paginatedUsers->items();

        // Menghilangkan field yang tidak diinginkan dari hasil pagination
        $paginationInfo = [
            'current_page' => $paginatedUsers->currentPage(),
            'last_page' => $paginatedUsers->lastPage(),
            'per_page' => $paginatedUsers->perPage(),
            'total' => $paginatedUsers->total(),
        ];

        // Hitung total keseluruhan byte-in dan byte-out di seluruh rentang tanggal sebelum pagination
        $totalBytesIn = $usersQuery->sum('bytes_in');
        $totalBytesOut = $usersQuery->sum('bytes_out');
        $totalBytes = $totalBytesIn + $totalBytesOut;

        // Buat response JSON dengan pagination yang sudah dimodifikasi
        return response()->json([
            'users' => $users, // Data pengguna tanpa links pagination
            'pagination' => $paginationInfo, // Hanya informasi pagination yang dibutuhkan
            'total_bytes_in' => $totalBytesIn,
            'total_bytes_out' => $totalBytesOut,
            'total_bytes' => $totalBytes,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotUsersByDateRange1(Request $request)
{
    try {
        // Mendapatkan nilai startDate dan endDate dari request body
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        // Validasi jika tanggal tidak diberikan
        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Tanggal awal dan akhir harus disediakan'], 400);
        }

        // Ubah format tanggal agar mencakup seluruh hari
        $startDate = $startDate . ' 00:00:00'; // Mulai dari awal hari
        $endDate = $endDate . ' 23:59:59';     // Hingga akhir hari

        // Query ke database untuk mendapatkan total bytes_in dan bytes_out per hari dalam rentang tanggal
        $logs = DB::table('user_bytes_log')
            ->select(
                DB::raw('DATE(timestamp) as date'),
                DB::raw('SUM(bytes_in) as total_bytes_in'),
                DB::raw('SUM(bytes_out) as total_bytes_out'),
                DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_bytes')
            )
            ->whereBetween('timestamp', [$startDate, $endDate]) // Filter berdasarkan rentang tanggal
            ->groupBy(DB::raw('DATE(timestamp)')) // Mengelompokkan berdasarkan tanggal
            ->orderBy(DB::raw('DATE(timestamp)'), 'asc')
            ->get();

        // Query untuk mendapatkan role yang unik dalam rentang tanggal
        $uniqueRoles = DB::table('user_bytes_log')
            ->select('role') // Ambil kolom role
            ->whereBetween('timestamp', [$startDate, $endDate]) // Filter berdasarkan rentang tanggal
            ->distinct() // Menghilangkan duplikat
            ->pluck('role'); // Mengambil hasil sebagai list role unik

        // Looping untuk mendapatkan pengguna terbesar per hari dan semua pengguna
        foreach ($logs as $log) {
            // Dapatkan pengguna terbesar per hari berdasarkan total_bytes (bytes_in + bytes_out)
            $largestUser = DB::table('user_bytes_log')
                ->select(
                    'user_name',
                    'role',
                    DB::raw('(bytes_in + bytes_out) as total_user_bytes')
                )
                ->whereDate('timestamp', $log->date) // Hanya untuk hari itu
                ->orderBy('total_user_bytes', 'desc')
                ->first();

            // Hitung persentase kontribusi pengguna terbesar terhadap total per hari
            if ($largestUser && $log->total_bytes > 0) {
                $largestUserPercentage = round(($largestUser->total_user_bytes / $log->total_bytes) * 100);
            } else {
                $largestUserPercentage = 0;
            }

            // Tambahkan informasi pengguna terbesar ke dalam log hari itu
            $log->largest_user = [
                'user_name' => $largestUser->user_name,
                'role' => $largestUser->role,
                'percentage' => $largestUserPercentage . "%" // Persen yang dibulatkan
            ];

            // Dapatkan semua pengguna pada hari tersebut
            $users = DB::table('user_bytes_log')
                ->select(
                    'user_name',
                    'role',
                    DB::raw('SUM(bytes_in) as total_bytes_in'),
                    DB::raw('SUM(bytes_out) as total_bytes_out'),
                    DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_user_bytes')
                )
                ->whereDate('timestamp', $log->date) // Hanya untuk hari itu
                ->groupBy('user_name', 'role') // Mengelompokkan berdasarkan nama pengguna dan role
                ->orderBy('total_user_bytes', 'desc')
                ->get();

            // Tambahkan informasi semua pengguna ke dalam log hari itu
            $log->all_users = $users;
        }

        // Hitung total keseluruhan byte-in dan byte-out di seluruh rentang tanggal
        $totalBytesIn = $logs->sum('total_bytes_in');
        $totalBytesOut = $logs->sum('total_bytes_out');
        $totalBytes = $totalBytesIn + $totalBytesOut;

        // Buat response JSON
        return response()->json([
            'details' => $logs,  // Detail byte-in, byte-out, total per hari, pengguna terbesar, dan semua pengguna
            'total_bytes_in' => $totalBytesIn,
            'total_bytes_out' => $totalBytesOut,
            'total_bytes' => $totalBytes,
            'unique_roles' => $uniqueRoles, // Menampilkan daftar role yang unik
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function updateUserBytesFromMikrotik()
{
    try {
        // Inisialisasi koneksi ke Mikrotik
        // Membuat koneksi ke MikroTik
        $endpoint = Cache::get('global_endpoint');

         // Dapatkan client berdasarkan endpoint
         $client = $this->getClient($endpoint);

        // Query untuk mendapatkan trafik dari semua user yang aktif
        $query = (new Query('/ip/hotspot/active/print'));

        // Eksekusi query untuk mendapatkan list user aktif beserta trafiknya
        $activeUsers = $client->query($query)->read();

        // Debug: Cetak seluruh data yang diterima untuk memverifikasi strukturnya
        Log::info("Active Users Data: " . print_r($activeUsers, true));

        // Loop melalui setiap user yang aktif
        foreach ($activeUsers as $user) {
            // Ambil nama pengguna dan trafik
            $userName = $user['user'];
            $bytesIn = $user['bytes-in'] ?? 0;
            $bytesOut = $user['bytes-out'] ?? 0;

            // Lakukan query untuk mendapatkan data user lengkap dari MikroTik berdasarkan nama pengguna
            $userDetailsQuery = (new Query('/ip/hotspot/user/print'))
                ->where('name', $userName);
            $userDetails = $client->query($userDetailsQuery)->read();

            // Default nilai role jika tidak ditemukan
            $role = 'default';

            // Cek apakah data user berhasil ditemukan dan ambil profil/role
            if (!empty($userDetails)) {
                $userDetails = $userDetails[0]; // Ambil data user pertama
                $role = $userDetails['profile'] ?? 'default';
            } else {
                Log::warning("Tidak dapat menemukan detail user untuk: $userName. Menggunakan role 'default'.");
            }

            // Debug: Pastikan nilai role sudah benar
            Log::info("User: $userName, Role: $role, Bytes In: $bytesIn, Bytes Out: $bytesOut");

            // Ambil log terakhir untuk perbandingan
            $existingLog = DB::table('user_bytes_log')
                ->where('user_name', $userName)
                ->orderBy('timestamp', 'desc')
                ->first();

            // Simpan hanya jika ada perubahan
            if (!$existingLog ||
                $existingLog->bytes_in != $bytesIn ||
                $existingLog->bytes_out != $bytesOut ||
                strtolower($existingLog->role) != strtolower($role)) {

                DB::table('user_bytes_log')->insert([
                    'user_name' => $userName,
                    'bytes_in' => $bytesIn,
                    'bytes_out' => $bytesOut,
                    'role' => $role,
                    'timestamp' => now(),
                ]);
            }
        }

        $this->updateUserBytesFromMikrotik1();

        return response()->json(['success' => 'Data berhasil diperbarui'], 200);

    } catch (\Exception $e) {
        Log::error('Error saat updateUserBytesFromMikrotik: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function updateUserBytesFromMikrotik1() {
        try {
            // Inisialisasi koneksi ke Mikrotik
            $endpoint = Cache::get('global_endpoint');

            // Kondisi untuk memeriksa endpoint
            if ($endpoint === 'third') {
                $table = 'wow_bandwidth_log';
                $queryType = 'interface';
            } else {
                $table = 'user_bytes_log';
                $queryType = 'hotspot';
            }

            // Membuat koneksi ke MikroTik
            $client = $this->getClient($endpoint);

            // Query berdasarkan jenis endpoint
            if ($queryType === 'hotspot') {
                $query = (new Query('/ip/hotspot/active/print'));
            } elseif ($queryType === 'interface') {
                $query = (new Query('/interface/print'));
            }

            // Eksekusi query
            $data = $client->query($query)->read();

            // Debug: Cetak seluruh data yang diterima
            Log::info("Data: " . print_r($data, true));

            if ($queryType === 'hotspot') {
                // Loop melalui setiap user yang aktif
                foreach ($data as $user) {
                    // Ambil nama pengguna dan trafik
                    $userName = $user['user'];
                    $bytesIn = $user['bytes-in'] ?? 0;
                    $bytesOut = $user['bytes-out'] ?? 0;
                    $role = $user['profile'] ?? 'default';

                    // Ambil log terakhir untuk perbandingan
                    $existingLog = DB::table($table)
                        ->where('user_name', $userName)
                        ->orderBy('timestamp', 'desc')
                        ->first();

                    // Simpan hanya jika ada perubahan
                    if (!$existingLog || $existingLog->bytes_in != $bytesIn || $existingLog->bytes_out != $bytesOut || strtolower($existingLog->role) != strtolower($role)) {
                        DB::table($table)->insert([
                            'user_name' => $userName,
                            'bytes_in' => $bytesIn,
                            'bytes_out' => $bytesOut,
                            'role' => $role,
                            'timestamp' => now(),
                        ]);
                    }
                }
            } elseif ($queryType === 'interface') {
                $lastTimestamp = DB::table('wow_bandwith_log')->max('timestamp');

                foreach ($data as $interface) {
                    $rx = $interface['rx-byte'];
                    $tx = $interface['tx-byte'];
                    $timestamp = now();
                    $interfaceName = $interface['name'];

                    // Ambil data terakhir
                    $lastData = DB::table('wow_bandwith_log')
                        ->where('interface_name', $interfaceName)
                        ->orderBy('timestamp', 'desc')
                        ->first();

                    if ($lastData) {
                        $deltaRx = $rx - $lastData->bytes_in;
                        $deltaTx = $tx - $lastData->bytes_out;
                    } else {
                        $deltaRx = $rx;
                        $deltaTx = $tx;
                    }

                    // Simpan data ke tabel
                    DB::table('wow_bandwith_log')->insert([
                        'interface_name' => $interfaceName,
                        'bytes_in' => $deltaRx,
                        'bytes_out' => $deltaTx,
                        'timestamp' => $timestamp,
                        'role' => 'daily'
                    ]);

                }
            }


            return response()->json(['success' => 'Data berhasil diperbarui'], 200);
        } catch (\Exception $e) {
            Log::error('Error saat updateUserBytesFromMikrotik: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function getHotspotUsersByUniqueRole(Request $request)
{
    try {
        $dbTable = $request->input('dbTable');
        $role = $request->input('role');
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        // Validasi
        if (!$dbTable || !$startDate || !$endDate) {
            return response()->json(['error' => 'Parameter harus lengkap'], 400);
        }

        // Atur tanggal
        $startDate = $startDate . ' 00:00:00';
        $endDate = $endDate . ' 23:59:59';

        // Tentukan nama kolom
        $columnName = ($dbTable == 'wow_bandwith_log') ? 'interface_name' : 'user_name';

        // Query database
        $query = DB::table($dbTable)
            ->select(
                DB::raw('DATE(timestamp) as date'),
                DB::raw('SUM(bytes_in) as total_bytes_in'),
                DB::raw('SUM(bytes_out) as total_bytes_out'),
                DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_bytes')
            )
            ->whereBetween('timestamp', [$startDate, $endDate]);

        // Filter role
        if ($role !== "All") {
            $query->where('role', $role);
        }

        // Group by tanggal dan urutkan hasil
        $logs = $query->groupBy(DB::raw('DATE(timestamp)'))
            ->orderBy(DB::raw('DATE(timestamp)'), 'asc')
            ->get();

        // Hitung total bytes
        $totalBytesIn = $logs->sum('total_bytes_in');
        $totalBytesOut = $logs->sum('total_bytes_out');
        $totalBytes = $totalBytesIn + $totalBytesOut;

        // Loop untuk mendapatkan pengguna terbesar per hari
        foreach ($logs as $log) {
            $largestUserQuery = DB::table($dbTable)
                ->select($columnName, DB::raw('(bytes_in + bytes_out) as total_user_bytes'))
                ->whereDate('timestamp', $log->date);

            // Filter role
            if ($role !== "All") {
                $largestUserQuery->where('role', $role);
            }

            $largestUser = $largestUserQuery->orderBy('total_user_bytes', 'desc')->first();

            // Hitung persentase pengguna terbesar
            $largestUserPercentage = ($largestUser && $log->total_bytes > 0) ? round(($largestUser->total_user_bytes / $log->total_bytes) * 100) : 0;

            // Tambahkan info pengguna terbesar ke log harian
            $log->largest_user = [
                $columnName => $largestUser->$columnName ?? null,
                'percentage' => $largestUserPercentage . "%"
            ];

            // Dapatkan semua pengguna untuk hari tersebut
            $usersQuery = DB::table($dbTable)
                ->select($columnName, DB::raw('SUM(bytes_in) as total_bytes_in'), DB::raw('SUM(bytes_out) as total_bytes_out'), DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_user_bytes'))
                ->whereDate('timestamp', $log->date)
                ->groupBy($columnName)
                ->orderBy('total_user_bytes', 'desc');

            // Filter role
            if ($role !== "All") {
                $usersQuery->where('role', $role);
            }

            $users = $usersQuery->get();

            // Tambahkan info semua pengguna ke log harian
            $log->all_users = $users;
        }

        // Return response JSON
        return response()->json([
            'details' => $logs,
            'total_bytes_in' => $totalBytesIn,
            'total_bytes_out' => $totalBytesOut,
            'total_bytes' => $totalBytes,
            'role' => $role,
            'dbTable' => $dbTable
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

}
