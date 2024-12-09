<?php

namespace App\Http\Controllers;

use App\Models\AkunKantor;
use App\Models\Menu;
use App\Models\Order;
use Carbon\Carbon;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Http\Request;
use ArelAyudhi\DhivaProdevWa;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MikrotikController extends Controller
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

    protected function sendwa($no_hp, $login_link)
    {
        $token = 'qeTAbqcqiZ6hooBgdtZ32ftcdney1SKGvDhLvS31A4g';
        $wablast = new DhivaProdevWa\ProdevMessages($token);

        // Isi pesan yang akan dikirimkan
        $message = "Halo, berikut adalah informasi login Hotspot Anda:\n\n" .
                "\n\nLink Login: $login_link\n\n" .
                "Pastikan Anda sudah login dan waktu akses Anda juga telah diperpanjang.";
        // Format nomor telepon tujuan
        $blast['phone'][0] = $no_hp;

        // Isi pesan ke dalam array blast
        $blast['message'][0] = $message;

        // Kirim pesan melalui broadcast
        $wablast->broadcast->sendInstan($blast);
    }

    public function calculateOrderDetails(array $menu_ids)
    {
        // Query database untuk mendapatkan harga dan waktu expiry berdasarkan menu_ids
        $menus = Menu::whereIn('id', $menu_ids)->get();

        // Jika tidak ada data menu ditemukan, return null atau error
        if ($menus->isEmpty()) {
            return null;
        }

        // Hitung total harga dan expiry_time
        $total_harga = $menus->sum('price'); // Field 'price' harus ada di tabel Menu
        $total_expiry_time = $menus->sum('expiry_time'); // Field 'expiry_time' harus ada di tabel Menu

        // Kembalikan hasil dalam bentuk objek
        return (object)[
            'total_harga' => $total_harga,
            'total_expiry_time' => $total_expiry_time
        ];
    }

    public function getHotspotUsersByte()
{
    try {
        $client = $this->getClient();

        // Query untuk mendapatkan daftar semua pengguna hotspot
        $userQuery = new Query('/ip/hotspot/user/print');
        $users = $client->query($userQuery)->read();

        // Query untuk mendapatkan pengguna yang sedang aktif
        $activeQuery = new Query('/ip/hotspot/active/print');
        $activeUsers = $client->query($activeQuery)->read();

        // Ambil total bytes dari session
        $totalBytesIn = session()->get('total_bytes_in', 0);
        $totalBytesOut = session()->get('total_bytes_out', 0);

        // Mapping pengguna aktif
        $activeUsersMap = [];
        foreach ($activeUsers as $activeUser) {
            $username = $activeUser['user'];
            $activeUsersMap[$username] = $activeUser;
        }

        // Ambil data pengguna dengan role dari database
        $userRoles = DB::table('user_bytes_log')->get()->keyBy('user_name'); // Mengambil data dari tabel user_bytes_log

        $modifiedUsers = array_map(function ($user) use (&$totalBytesIn, &$totalBytesOut, $activeUsersMap, $userRoles) {
            $newUser = [];
            foreach ($user as $key => $value) {
                $newKey = str_replace('.id', 'id', $key);
                $newUser[$newKey] = $value;
            }

            // Cek apakah pengguna aktif, lalu tambahkan bytes-in dan bytes-out
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

                    $previousTimestamp = strtotime($existingUser->timestamp);
                    $currentTimestamp = time();
                    $duration = $currentTimestamp - $previousTimestamp;

                    if ($duration > 0) {
                        // Konversi dan pembulatan ke Mbps
                        $newUser['average_bytes_in_per_second'] = round(($newUser['bytes-in'] * 8) / 1000000 / $duration, 2);
                        $newUser['average_bytes_out_per_second'] = round(($newUser['bytes-out'] * 8) / 1000000 / $duration, 2);
                    } else {
                        $newUser['average_bytes_in_per_second'] = 0;
                        $newUser['average_bytes_out_per_second'] = 0;
                    }
                } else {
                    $newUser['bytes-in'] = 0;
                    $newUser['bytes-out'] = 0;
                    $newUser['average_bytes_in_per_second'] = 0;
                    $newUser['average_bytes_out_per_second'] = 0;
                }
            }

            // Menambahkan role ke pengguna (berdasarkan user_bytes_log)
            $newUser['role'] = isset($userRoles[$user['name']]) ? $userRoles[$user['name']]->role : 'unknown';

            $totalBytesIn += isset($newUser['bytes-in']) ? (int)$newUser['bytes-in'] : 0;
            $totalBytesOut += isset($newUser['bytes-out']) ? (int)$newUser['bytes-out'] : 0;

            $newUser['total-bytes'] = $newUser['bytes-in'] + $newUser['bytes-out'];

            // Simpan log bytes pengguna jika ada perubahan
            if (!isset($existingUser) || $existingUser->bytes_in != $newUser['bytes-in'] || $existingUser->bytes_out != $newUser['bytes-out']) {
                DB::table('user_bytes_log')->insert([
                    'user_name' => $newUser['name'],
                    'role' => $newUser['role'], // Simpan role ke log
                    'bytes_in' => $newUser['bytes-in'],
                    'bytes_out' => $newUser['bytes-out'],
                    'timestamp' => now()
                ]);
            }

            return $newUser;
        }, $users);

        // Sortir pengguna berdasarkan total bytes
        usort($modifiedUsers, function($a, $b) {
            return $b['total-bytes'] - $a['total-bytes'];
        });

        // Paginasi
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 5;
        $currentItems = array_slice($modifiedUsers, ($currentPage - 1) * $perPage, $perPage);
        $pagination = new LengthAwarePaginator($currentItems, count($modifiedUsers), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath()
        ]);

        // Simpan total bytes di session
        session()->put('total_bytes_in', $totalBytesIn);
        session()->put('total_bytes_out', $totalBytesOut);

        return response()->json([
            'total_user' => $pagination->total(),
            'total_pages' => $pagination->lastPage(),
            'current_page' => $pagination->currentPage(),
            'users' => $pagination->items(),
            'total_bytes_in' => $totalBytesIn,
            'total_bytes_out' => $totalBytesOut
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotUserByPhoneNumber($no_hp)
{
    try {
        $client = $this->getClient();

        // Query untuk mendapatkan pengguna berdasarkan nomor telepon
        $query = new Query('/ip/hotspot/user/print');
        $query->where('name', $no_hp); // 'name' adalah field untuk username di MikroTik

        $users = $client->query($query)->read();

        if (empty($users)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Ambil pengguna pertama (jika ada banyak)
        $user = $users[0];

        // Ubah .id menjadi id jika ada
        $modifiedUser = [];
        foreach ($user as $key => $value) {
            // Ganti .id dengan id pada key
            $newKey = str_replace('.id', 'id', $key);
            $modifiedUser[$newKey] = $value;
        }

        // Cek apakah pengguna memiliki 'profile' yang dapat kita gunakan untuk mengambil link
        $profileName = $user['profile'] ?? null;

        // Query untuk mendapatkan link dari tabel user_profile_link berdasarkan profile name
        $link = null;
        if ($profileName) {
            $link = DB::table('user_profile_link')
                ->where('name', $profileName)
                ->value('link');
        }

        // Tambahkan link ke data user yang sudah diubah
        $modifiedUser['link'] = $link ?? 'No link found';

        // Format response untuk mengembalikan data pengguna yang sudah diubah
        return response()->json(['user' => $modifiedUser]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotUsersByProfileName($profile_name)
{
    try {
        // Inisiasi client untuk berkomunikasi dengan MikroTik
        $client = $this->getClient();

        // Membuat query untuk mendapatkan semua pengguna berdasarkan profile_name
        $query = new Query('/ip/hotspot/user/print');
        $query->where('profile', $profile_name); // Filter berdasarkan profile_name

        // Eksekusi query untuk mendapatkan hasilnya
        $users = $client->query($query)->read();

        // Cek apakah tidak ada pengguna yang ditemukan
        if (empty($users)) {
            // Jika tidak ada data, kembalikan response hanya dengan profile_name dan array kosong
            return response()->json([
                'users' => [],
                'total_bytes_in' => 0,
                'total_bytes_out' => 0
            ], 200);
        }

        // Array untuk menyimpan semua pengguna yang sudah dimodifikasi
        $modifiedUsers = [];

        // Variabel untuk menyimpan total bytes-in dan bytes-out
        $totalBytesIn = 0;
        $totalBytesOut = 0;

        // Loop melalui setiap user yang ditemukan
        foreach ($users as $user) {
            $modifiedUser = [];
            foreach ($user as $key => $value) {
                // Ganti .id dengan id pada key
                $newKey = str_replace('.id', 'id', $key);
                $modifiedUser[$newKey] = $value;
            }

            // Jika ada bytes-in dan bytes-out, tambahkan ke total
            if (isset($user['bytes-in'])) {
                $totalBytesIn += (int)$user['bytes-in'];
            }
            if (isset($user['bytes-out'])) {
                $totalBytesOut += (int)$user['bytes-out'];
            }

            // Masukkan user yang sudah dimodifikasi ke dalam array
            $modifiedUsers[] = $modifiedUser;
        }

        // Kembalikan response dengan profile_name dan data pengguna yang sudah diubah
        return response()->json([
            'users' => $modifiedUsers,
            'total_bytes_in' => $totalBytesIn,
            'total_bytes_out' => $totalBytesOut
        ], 200);

    } catch (\Exception $e) {
        // Tangani jika terjadi error
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function addHotspotUser1(Request $request)
{
    // Validasi input
    $request->validate([
        'no_hp' => 'required|string|max:20',
        'name' => 'sometimes|required|string|max:255',
        'menu_ids' => 'required|array',
        'profile' => 'nullable|string|max:50' // Profile is nullable
    ]);

    $profile = $request->input('profile', 'customer'); // Default 'customer' jika tidak diberikan
    $no_hp = $request->input('no_hp');
    $menu_ids = $request->input('menu_ids');
    $name = $request->input('name', null); // Optional untuk extend

    try {
        $client = $this->getClient();

        // Cek apakah user sudah ada di MikroTik
        $checkQuery = (new Query('/ip/hotspot/user/print'))->where('name', $no_hp);
        $existingUsers = $client->query($checkQuery)->read();

        // Set default expiry time to 6 hours (360 minutes)
        $expiryExtensionHours = 6;
        $defaultExpiryTime = Carbon::now()->addHours($expiryExtensionHours);

        if (!empty($existingUsers)) {
            $comment = $existingUsers[0]['comment'] ?? '';
            $existingName = $name;
            $expiryTime = null;

            // Cek status di comment
            $isInactive = strpos($comment, 'status: inactive') !== false;
            $isActive = strpos($comment, 'status: active') !== false;

            if (strpos($comment, 'Expiry:') !== false) {
                $parts = explode(', ', $comment);
                foreach ($parts as $part) {
                    if (strpos($part, 'Expiry:') === 0) {
                        $expiryTime = Carbon::parse(trim(substr($part, strlen('Expiry: '))));
                    } else {
                        $existingName = $part;
                    }
                }
            }

            // Jika status user adalah inactive, hanya beritahu tanpa memperbarui apapun
            if ($isInactive) {
                return response()->json([
                    'message' => 'User ditemukan namun dalam status inactive. Tidak ada perubahan yang dilakukan.'
                ]);
            }

            // Update expiry time jika user sudah active
            if ($isActive) {
                if ($expiryTime && $expiryTime->greaterThan(Carbon::now())) {
                    $newExpiryTime = $expiryTime->addHours($expiryExtensionHours);
                } else {
                    $newExpiryTime = $defaultExpiryTime;
                }

                // Update komentar dengan status active, nama, dan expiry baru
                $updatedComment = "status: active, {$existingName}, Expiry: " . $newExpiryTime->format('Y-m-d H:i:s');

                // Update user di MikroTik dan set status
                $updateUserQuery = (new Query('/ip/hotspot/user/set'))
                    ->equal('.id', $existingUsers[0]['.id'])
                    ->equal('comment', $updatedComment);

                $client->query($updateUserQuery)->read();

                foreach ($menu_ids as $menu_id) {
                    $existingOrder = Order::where('no_hp', $no_hp)->where('menu_id', $menu_id)->first();

                    if ($existingOrder) {
                        $existingOrder->update([
                            'expiry_at' => $newExpiryTime->format('Y-m-d H:i:s')
                        ]);
                    } else {
                        Order::create([
                            'no_hp' => $no_hp,
                            'menu_id' => $menu_id,
                            'expiry_at' => $newExpiryTime->format('Y-m-d H:i:s')
                        ]);
                    }
                }

                if (!empty($no_hp)) {
                    $loginLink = "http://192.168.51.1/login?username={$no_hp}&password={$no_hp}";
                    $this->sendwa($no_hp, $loginLink);
                } else {
                    return response()->json([
                        'message' => 'Nomor HP tidak valid atau kosong.',
                        'status' => 'failed'
                    ], 400);
                }

                // Panggil fungsi dari controller lain untuk memperbarui data
                $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
                $hotspotController->getHotspotUsers1();

                return response()->json([
                    'message' => 'User diperpanjang dan login berhasil. Expiry time: ' . $newExpiryTime->format('Y-m-d H:i:s'),
                    'login_link' => $loginLink ?? null,
                    'Waktu Defaultnya' => '6 Jam',
                    'note' => 'Ini Link Login kalo lupa ya, kalo kamu udah login gak usah di pake sama waktu kamu juga udah diextend'
                ]);
            }
        } else {
            $newExpiryTime = $defaultExpiryTime;

            // Tambahkan user baru ke MikroTik dan set status ke inactive
            $addUserQuery = (new Query('/ip/hotspot/user/add'))
                ->equal('name', $no_hp)
                ->equal('password', $no_hp)
                ->equal('profile', $profile)
                ->equal('comment', "status: inactive, name: {$name}, Expiry: {$newExpiryTime->format('Y-m-d H:i:s')}");

            $client->query($addUserQuery)->read();

            foreach ($menu_ids as $menu_id) {
                Order::create([
                    'no_hp' => $no_hp,
                    'menu_id' => $menu_id,
                    'expiry_at' => $newExpiryTime->format('Y-m-d H:i:s')
                ]);
            }

            if (!empty($no_hp)) {
                $loginLink = "http://192.168.51.1/login?username={$no_hp}&password={$no_hp}";
                $this->sendwa($no_hp, $loginLink);
            } else {
                return response()->json([
                    'message' => 'Nomor HP tidak valid atau kosong.',
                    'status' => 'failed'
                ], 400);
            }

            // Panggil fungsi dari controller lain untuk memperbarui data
            $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            $hotspotController->getHotspotUsers1();

            return response()->json([
                'message' => 'User baru ditambahkan dan login berhasil. Expiry time: ' . $newExpiryTime->format('Y-m-d H:i:s'),
                'login_link' => $loginLink ?? null,
                'Waktu Defaultnya' => '6 Jam',
                'note' => 'Ini Link Login kalo lupa ya, kalo kamu udah login gak usah di pake sama waktu kamu juga udah diextend'
            ]);
        }

    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
    }

    public function addHotspotUser(Request $request)
{
    $request->validate([
        'no_hp' => 'required|string|max:20',
        'name' => 'sometimes|required|string|max:255',
        'profile' => 'nullable|string|max:50'
    ]);

    $profile = $request->input('profile', 'customer');
    $no_hp = $request->input('no_hp');
    $name = $request->input('name', null);

    try {
        $client = $this->getClient();

        $checkQuery = (new Query('/ip/hotspot/user/print'))->where('name', $no_hp);
        $existingUsers = $client->query($checkQuery)->read();

        if (!empty($existingUsers)) {
            return response()->json(['message' => 'User sudah ada di MikroTik.'], 409);
        } else {
            $addUserQuery = (new Query('/ip/hotspot/user/add'))
                ->equal('name', $no_hp)
                ->equal('password', $no_hp)
                ->equal('profile', $profile)
                ->equal('disabled', 'false')
                ->equal('comment', "{$name}");

            $client->query($addUserQuery)->read();

            if (in_array($profile, ['Owner', 'Staff'])) {
                AkunKantor::create([
                    'no_hp' => $no_hp,
                    'name' => $name,
                    'profile' => $profile,
                ]);
            }

            // Panggil fungsi dari controller lain
            $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            $hotspotController->getHotspotUsers1();

            return response()->json([
                'message' => 'User baru ditambahkan tanpa expiry time.',
            ], 201);
        }
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
    }

    public function editHotspotUser(Request $request, $no_hp)
    {
        // Validasi input
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'profile' => 'nullable|string|max:50',
            'comment' => 'sometimes|required|string|max:255', // Optional, jika ingin update comment
            'disabled' => 'sometimes|required|string', // Validasi untuk field disabled (true atau false)
        ]);

        try {
            $client = $this->getClient();

            // Cek apakah user sudah ada di MikroTik
            $checkQuery = (new Query('/ip/hotspot/user/print'))->where('name', $no_hp);
            $existingUsers = $client->query($checkQuery)->read();

            if (empty($existingUsers)) {
                // Jika user tidak ditemukan
                return response()->json(['message' => 'User tidak ditemukan.'], 404);
            }

            // Ambil user yang ada
            $userId = $existingUsers[0]['.id'];

            // Siapkan query untuk update user
            $updateUserQuery = (new Query('/ip/hotspot/user/set'))
                ->equal('.id', $userId);

            // Update name jika diberikan
            if ($request->has('name')) {
                $updateUserQuery->equal('name', $request->input('name'));
            }

            // Update profile jika diberikan
            if ($request->has('profile')) {
                $updateUserQuery->equal('profile', $request->input('profile'));
            }

            // Update comment jika diberikan
            if ($request->has('comment')) {
                $updateUserQuery->equal('comment', $request->input('comment'));
            }

            // Update disabled menggunakan true/false
            if ($request->has('disabled')) {
                $disabledInput = $request->input('disabled');

                if ($disabledInput === 'true') {
                    $disabledValue = 'true';
                } elseif ($disabledInput === 'false') {
                    $disabledValue = 'false';
                } else {
                    return response()->json(['error' => 'Invalid value for disabled field.'], 400);
                }

                $updateUserQuery->equal('disabled', $disabledValue);
            }

            // Eksekusi query untuk mengupdate user
            $client->query($updateUserQuery)->read();

            // Panggil fungsi dari controller lain setelah user diperbarui
            $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            $hotspotController->getHotspotUsers1();

            // Kembalikan pesan sukses
            return response()->json(['message' => 'User berhasil diperbarui.'], 200);

        } catch (\Exception $e) {
            // Tampilkan pesan error
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function updateAllHotspotUsersByPhoneNumber()
{
    try {
        $client = $this->getClient();

        // Ambil semua pengguna yang saat ini aktif dari tab "Active" MikroTik
        $getActiveUsersQuery = new Query('/ip/hotspot/active/print');
        $activeUsers = $client->query($getActiveUsersQuery)->read();

        // Jika tidak ada pengguna aktif, hentikan proses dan beri pesan
        if (empty($activeUsers)) {
            return response()->json(['message' => 'Tidak ada pengguna aktif.'], 200);
        }

        // Ambil nomor telepon pengguna yang aktif dari daftar pengguna aktif
        $activePhoneNumbers = array_column($activeUsers, 'user');

        // Loop melalui setiap nomor telepon yang aktif
        foreach ($activePhoneNumbers as $no_hp) {
            // Cek semua pengguna dengan nama yang sama di daftar utama "Users" di MikroTik
            $getUserQuery = (new Query('/ip/hotspot/user/print'))->where('name', $no_hp);
            $users = $client->query($getUserQuery)->read();

            // Jika pengguna tidak ditemukan di daftar utama "Users", lewati
            if (empty($users)) {
                continue;
            }

            // Loop untuk setiap pengguna yang cocok di daftar utama "Users"
            foreach ($users as $user) {
                $userId = $user['.id'];
                $comment = $user['comment'] ?? ''; // Komentar yang mungkin menyimpan status

                // Jika pengguna sudah memiliki status active, lewati pengguna ini
                if (strpos($comment, 'status: active') !== false) {
                    continue;
                }

                // Ambil pesanan yang masih berlaku (expiry_at kurang dari 5 menit dari sekarang)
                $validOrders = Order::where('no_hp', $no_hp)
                    ->where('expiry_at', '>', Carbon::now()->subMinutes(5))
                    ->get();

                // Jika tidak ada pesanan yang masih berlaku, lewati pengguna ini
                if ($validOrders->isEmpty()) {
                    continue;
                }

                // Ambil menu_id dari pesanan yang masih berlaku
                $menu_ids = $validOrders->pluck('menu_id')->toArray();

                // Hitung total waktu kadaluarsa berdasarkan menu_ids
                $orderDetails = $this->calculateOrderDetails($menu_ids);

                // Jika tidak ada waktu kadaluarsa yang valid, lewati pengguna ini
                if (is_null($orderDetails) || $orderDetails->total_expiry_time <= 0) {
                    continue;
                }

                // Set waktu kadaluarsa baru mulai dari sekarang
                $newExpiryTime = Carbon::now()->addMinutes($orderDetails->total_expiry_time);

                // Ambil 'name' dari komentar jika sudah ada, atau gunakan $no_hp jika tidak ada
                if (preg_match('/name: ([^,]+)/', $comment, $matches)) {
                    $name = $matches[1];
                } else {
                    $name = $no_hp;
                }

                // Update pengguna di MikroTik dengan komentar baru tanpa mengganti 'name' yang sudah ada
                $updatedComment = "status: active, name: {$name}, Expiry: {$newExpiryTime->format('Y-m-d H:i:s')}";
                $updateUserQuery = (new Query('/ip/hotspot/user/set'))
                    ->equal('.id', $userId)
                    ->equal('comment', $updatedComment);

                $client->query($updateUserQuery)->read();

                // Update waktu kadaluarsa di database untuk setiap pesanan yang masih berlaku
                foreach ($validOrders as $order) {
                    $order->update([
                        'expiry_at' => $newExpiryTime->format('Y-m-d H:i:s')
                    ]);
                }
            }
        }

        $this->deleteExpiredHotspotUsers();

        return response()->json([
            'message' => 'Komentar dan waktu kadaluarsa semua pengguna yang sesuai berhasil diperbarui.',
        ]);

    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
    }

    public function deleteExpiredHotspotUsers()
    {
        // Lock to avoid simultaneous executions
        $lock = Cache::lock('mikrotik_hotspot_user_operation', 10);

        if ($lock->get()) {
            try {
                // Initialize MikroTik client
                $client = $this->getClient();
                $query = new Query('/ip/hotspot/user/print');
                $users = $client->query($query)->read();

                foreach ($users as $user) {
                    // Check if there is an 'Expiry' comment
                    if (isset($user['comment']) && preg_match('/Expiry:\s*([\d\/\-:\s]+)/', $user['comment'], $matches)) {
                        try {
                            $expiryTime = null;

                            // Attempt to parse the expiry date with different formats
                            if (strpos($matches[1], '/') !== false) {
                                // Format with slashes 'Y/m/d H:i:s'
                                $expiryTime = Carbon::createFromFormat('Y/m/d H:i:s', $matches[1])->setTimezone(config('app.timezone'));
                            } elseif (strpos($matches[1], '-') !== false) {
                                // Format with dashes 'Y-m-d H:i:s'
                                $expiryTime = Carbon::createFromFormat('Y-m-d H:i:s', $matches[1])->setTimezone(config('app.timezone'));
                            }

                            // If expiryTime is set, proceed to check the current time
                            if ($expiryTime && Carbon::now()->greaterThanOrEqualTo($expiryTime)) {

                                // Step 1: Delete the hotspot user
                                $deleteQuery = (new Query('/ip/hotspot/user/remove'))->equal('.id', $user['.id']);
                                $client->query($deleteQuery)->read();

                                // Step 2: Disconnect any active sessions associated with the user
                                $activeSessionsQuery = (new Query('/ip/hotspot/active/print'))
                                    ->where('user', $user['name']); // Filter by the username

                                $activeSessions = $client->query($activeSessionsQuery)->read();

                                // Loop through active sessions and remove them
                                foreach ($activeSessions as $session) {
                                    $terminateSessionQuery = (new Query('/ip/hotspot/active/remove'))
                                        ->equal('.id', $session['.id']); // Terminate the session

                                    $client->query($terminateSessionQuery)->read();
                                }
                            }
                        } catch (\Exception $e) {
                            // Continue to the next user if there's an error
                            continue;
                        }
                    }
                }

                // Panggil fungsi dari controller lain setelah penghapusan selesai
                $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
                $hotspotController->getHotspotUsers1();

                return response()->json(['message' => 'Expired hotspot users and their active connections deleted successfully']);
            } catch (\Exception $e) {
                // Catch all other errors
                return response()->json(['error' => $e->getMessage()], 500);
            } finally {
                // Release the lock after the operation
                $lock->release();
            }
        } else {
            return response()->json(['message' => 'Another hotspot user operation is in progress'], 429);
        }
        }




}
