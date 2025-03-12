<?php

namespace App\Http\Controllers;

use App\Models\AkunKantor;
use App\Models\Menu;
use App\Models\Order;
use Carbon\Carbon;
use ArelAyudhi\DhivaProdevWa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RouterOS\Query;

class MikrotikController extends CentralController
{
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
        $menus = Menu::whereIn('id', $menu_ids)->get();

        if ($menus->isEmpty()) {
            return null;
        }

        $total_harga = $menus->sum('price');
        $total_expiry_time = $menus->sum('expiry_time');

        return (object)[
            'total_harga' => $total_harga,
            'total_expiry_time' => $total_expiry_time
        ];
    }


    public function getHotspotUserByPhoneNumber($no_hp)
{
    try {
        $client = $this->getClientLogin();

        $query = new Query('/ip/hotspot/user/print');
        $query->where('name', $no_hp);

        $users = $client->query($query)->read();

        if (empty($users)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user = $users[0];

        $modifiedUser = [];
        foreach ($user as $key => $value) {
            $newKey = str_replace('.id', 'id', $key);
            $modifiedUser[$newKey] = $value;
        }

        $profileName = $user['profile'] ?? null;
        $comment = $user['comment'] ?? 'No comment';

        $link = null;
        if ($profileName) {
            $link = DB::table('user_profile_link')
                ->where('name', $profileName)
                ->value('link');
        }

        $modifiedUser['link'] = $link ?? 'No link found';
        $modifiedUser['comment'] = $comment;

        return response()->json(['user' => $modifiedUser]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }


    public function getHotspotUsersByProfileName($profile_name)
{
    try {
         $client = $this->getClientLogin();

        $query = new Query('/ip/hotspot/user/print');
        $query->where('profile', $profile_name);

        $users = $client->query($query)->read();

        if (empty($users)) {

            return response()->json([
                'users' => [],
                'total_bytes_in' => 0,
                'total_bytes_out' => 0
            ], 200);
        }

        $modifiedUsers = [];

        $totalBytesIn = 0;
        $totalBytesOut = 0;

        foreach ($users as $user) {
            $modifiedUser = [];
            foreach ($user as $key => $value) {
                $newKey = str_replace('.id', 'id', $key);
                $modifiedUser[$newKey] = $value;
            }

            if (isset($user['bytes-in'])) {
                $totalBytesIn += (int)$user['bytes-in'];
            }
            if (isset($user['bytes-out'])) {
                $totalBytesOut += (int)$user['bytes-out'];
            }

            $modifiedUsers[] = $modifiedUser;
        }

        return response()->json([
            'users' => $modifiedUsers,
            'total_bytes_in' => $totalBytesIn,
            'total_bytes_out' => $totalBytesOut
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function addHotspotUser1(Request $request)
{
    $request->validate([
        'no_hp' => 'required|string|max:20',
        'name' => 'sometimes|required|string|max:255',
        'menu_ids' => 'required|array',
        'profile' => 'nullable|string|max:50'
    ]);

    $profile = $request->input('profile', 'customer');
    $no_hp = $request->input('no_hp');
    $menu_ids = $request->input('menu_ids');
    $name = $request->input('name', null);

    try {
         $client = $this->getClient();

        $checkQuery = (new Query('/ip/hotspot/user/print'))->where('name', $no_hp);
        $existingUsers = $client->query($checkQuery)->read();

        $expiryExtensionHours = 6;
        $defaultExpiryTime = Carbon::now()->addHours($expiryExtensionHours);

        if (!empty($existingUsers)) {
            $comment = $existingUsers[0]['comment'] ?? '';
            $existingName = $name;
            $expiryTime = null;

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

            if ($isInactive) {
                return response()->json([
                    'message' => 'User ditemukan namun dalam status inactive. Tidak ada perubahan yang dilakukan.'
                ]);
            }

            if ($isActive) {
                if ($expiryTime && $expiryTime->greaterThan(Carbon::now())) {
                    $newExpiryTime = $expiryTime->addHours($expiryExtensionHours);
                } else {
                    $newExpiryTime = $defaultExpiryTime;
                }

                $updatedComment = "status: active, {$existingName}, Expiry: " . $newExpiryTime->format('Y-m-d H:i:s');

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

                // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
                // $hotspotController->getHotspotUsers1();

                return response()->json([
                    'message' => 'User diperpanjang dan login berhasil. Expiry time: ' . $newExpiryTime->format('Y-m-d H:i:s'),
                    'login_link' => $loginLink ?? null,
                    'Waktu Defaultnya' => '6 Jam',
                    'note' => 'Ini Link Login kalo lupa ya, kalo kamu udah login gak usah di pake sama waktu kamu juga udah diextend'
                ]);
            }
        } else {
            $newExpiryTime = $defaultExpiryTime;

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

            // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            // $hotspotController->getHotspotUsers1();

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
        'profile' => 'nullable|string|max:50'
    ]);

    $profile = $request->input('profile', 'customer');
    $no_hp = $request->input('no_hp');

    try {
         $client = $this->getClientLogin();

        $checkQuery = (new Query('/ip/hotspot/user/print'))->where('name', $no_hp);
        $existingUsers = $client->query($checkQuery)->read();

        if (!empty($existingUsers)) {
            return response()->json(['message' => 'User sudah ada di MikroTik.'], 409);
        } else {
            $addUserQuery = (new Query('/ip/hotspot/user/add'))
                ->equal('name', $no_hp)
                ->equal('password', $no_hp)
                ->equal('profile', $profile)
                ->equal('disabled', 'false');

            $client->query($addUserQuery)->read();

            return response()->json([
                'message' => 'User baru ditambahkan tanpa expiry time.',
                'no_hp' => $no_hp,
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
            'comment' => 'sometimes|required|string|max:255',
            'disabled' => 'sometimes|required|string',
        ]);

        try {

         $client = $this->getClient();

            $checkQuery = (new Query('/ip/hotspot/user/print'))->where('name', $no_hp);
            $existingUsers = $client->query($checkQuery)->read();

            if (empty($existingUsers)) {
                return response()->json(['message' => 'User tidak ditemukan.'], 404);
            }

            $userId = $existingUsers[0]['.id'];

            $updateUserQuery = (new Query('/ip/hotspot/user/set'))
                ->equal('.id', $userId);

            if ($request->has('name')) {
                $updateUserQuery->equal('name', $request->input('name'));
            }

            if ($request->has('profile')) {
                $updateUserQuery->equal('profile', $request->input('profile'));
            }

            if ($request->has('comment')) {
                $updateUserQuery->equal('comment', $request->input('comment'));
            }

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

            $client->query($updateUserQuery)->read();

            // Panggil fungsi dari controller lain setelah user diperbarui
            // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            // $hotspotController->getHotspotUsers1();
            return response()->json(['message' => 'User berhasil diperbarui.'], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function updateAllHotspotUsersByPhoneNumber()
{
    try {
         $client = $this->getClient();

        $getActiveUsersQuery = new Query('/ip/hotspot/active/print');
        $activeUsers = $client->query($getActiveUsersQuery)->read();

        if (empty($activeUsers)) {
            return response()->json(['message' => 'Tidak ada pengguna aktif.'], 200);
        }

        $activePhoneNumbers = array_column($activeUsers, 'user');

        foreach ($activePhoneNumbers as $no_hp) {
            $getUserQuery = (new Query('/ip/hotspot/user/print'))->where('name', $no_hp);
            $users = $client->query($getUserQuery)->read();

            if (empty($users)) {
                continue;
            }

            foreach ($users as $user) {
                $userId = $user['.id'];
                $comment = $user['comment'] ?? '';

                if (strpos($comment, 'status: active') !== false) {
                    continue;
                }

                $validOrders = Order::where('no_hp', $no_hp)
                    ->where('expiry_at', '>', Carbon::now()->subMinutes(5))
                    ->get();

                if ($validOrders->isEmpty()) {
                    continue;
                }

                $menu_ids = $validOrders->pluck('menu_id')->toArray();

                $orderDetails = $this->calculateOrderDetails($menu_ids);

                if (is_null($orderDetails) || $orderDetails->total_expiry_time <= 0) {
                    continue;
                }

                $newExpiryTime = Carbon::now()->addMinutes($orderDetails->total_expiry_time);

                if (preg_match('/name: ([^,]+)/', $comment, $matches)) {
                    $name = $matches[1];
                } else {
                    $name = $no_hp;
                }

                $updatedComment = "status: active, name: {$name}, Expiry: {$newExpiryTime->format('Y-m-d H:i:s')}";
                $updateUserQuery = (new Query('/ip/hotspot/user/set'))
                    ->equal('.id', $userId)
                    ->equal('comment', $updatedComment);

                $client->query($updateUserQuery)->read();

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
         $client = $this->getClient();
                $query = new Query('/ip/hotspot/user/print');
                $users = $client->query($query)->read();

                foreach ($users as $user) {
                    if (isset($user['comment']) && preg_match('/Expiry:\s*([\d\/\-:\s]+)/', $user['comment'], $matches)) {
                        try {
                            $expiryTime = null;

                            if (strpos($matches[1], '/') !== false) {
                                $expiryTime = Carbon::createFromFormat('Y/m/d H:i:s', $matches[1])->setTimezone(config('app.timezone'));
                            } elseif (strpos($matches[1], '-') !== false) {
                                $expiryTime = Carbon::createFromFormat('Y-m-d H:i:s', $matches[1])->setTimezone(config('app.timezone'));
                            }

                            if ($expiryTime && Carbon::now()->greaterThanOrEqualTo($expiryTime)) {

                                $deleteQuery = (new Query('/ip/hotspot/user/remove'))->equal('.id', $user['.id']);
                                $client->query($deleteQuery)->read();

                                $activeSessionsQuery = (new Query('/ip/hotspot/active/print'))
                                    ->where('user', $user['name']);
                                $activeSessions = $client->query($activeSessionsQuery)->read();

                                foreach ($activeSessions as $session) {
                                    $terminateSessionQuery = (new Query('/ip/hotspot/active/remove'))
                                        ->equal('.id', $session['.id']);

                                    $client->query($terminateSessionQuery)->read();
                                }
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }

                // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
                // $hotspotController->getHotspotUsers1();

                return response()->json(['message' => 'Expired hotspot users and their active connections deleted successfully']);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            } finally {
                $lock->release();
            }
        } else {
            return response()->json(['message' => 'Another hotspot user operation is in progress'], 429);
        }
    }
}
