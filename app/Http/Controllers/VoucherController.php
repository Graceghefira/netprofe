<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RouterOS\Query;

class VoucherController extends CentralController
{

    public function deleteExpiredHotspotUsers($mikrotikConfig)
{
    try {
        $client = $this->getClientVoucher($mikrotikConfig);

        $getUsersQuery = new Query('/ip/hotspot/user/print');
        $users = $client->query($getUsersQuery)->read();

        foreach ($users as $user) {
            if (isset($user['comment']) && str_contains($user['comment'], 'status: active')) {
                preg_match('/expiry: ([\d\- :]+)/', $user['comment'], $matches);

                if (!empty($matches[1])) {
                    $expiryTime = strtotime($matches[1]);
                    $currentTime = time();


                    if ($currentTime > $expiryTime) {
                        $getActiveUsersQuery = new Query('/ip/hotspot/active/print');
                        $activeUsers = $client->query($getActiveUsersQuery)->read();

                        foreach ($activeUsers as $activeUser) {
                            if ($activeUser['user'] === $user['name']) {
                                $deleteActiveQuery = (new Query('/ip/hotspot/active/remove'))
                                    ->equal('.id', $activeUser['.id']);
                                $client->query($deleteActiveQuery)->read();
                            }
                        }

                        $deleteUserQuery = (new Query('/ip/hotspot/user/remove'))
                            ->equal('.id', $user['.id']);
                        $client->query($deleteUserQuery)->read();
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Expired users deleted successfully from active sessions and users list.'
        ]);

    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
    }

    public function updateAllHotspotUsersByPhoneNumber($mikrotikConfig)
{
    try {
        // Menggunakan data mikrotik_config yang diteruskan
        $client = $this->getClientVoucher($mikrotikConfig);

        // Query untuk mendapatkan pengguna aktif
        $getActiveUsersQuery = new Query('/ip/hotspot/active/print');
        $activeUsers = $client->query($getActiveUsersQuery)->read();

        if (empty($activeUsers)) {
            return response()->json(['message' => 'Tidak ada pengguna aktif.'], 200);
        }

        // Extract username dari pengguna aktif
        $activeUsernames = array_column($activeUsers, 'user');

        // Proses setiap username yang aktif
        foreach ($activeUsernames as $username) {
            // Query untuk mendapatkan detail user
            $getUserQuery = (new Query('/ip/hotspot/user/print'))->where('name', $username);
            $users = $client->query($getUserQuery)->read();

            if (empty($users)) {
                continue; // Skip jika user tidak ditemukan
            }

            foreach ($users as $user) {
                $userId = $user['.id'];
                $comment = $user['comment'] ?? ''; // Menggunakan null coalescing untuk menghindari error jika 'comment' tidak ada

                // Cek jika status sudah aktif, jika ya, lewati
                if (strpos($comment, 'status: active') !== false) {
                    continue;
                }

                // Cari voucher berdasarkan username
                $voucher = DB::table('voucher_lists')->where('name', $username)->first();

                // Cek jika voucher tidak ditemukan
                if (!$voucher) {
                    Log::warning("Voucher tidak ditemukan untuk username: {$username}");
                    continue;
                }

                // Pastikan waktu kadaluarsa valid
                $voucher_hours = (int)($voucher->waktu ?? 0);
                $newExpiryTime = Carbon::now()->addHours($voucher_hours);

                // Ambil nama pengguna dari comment jika ada
                if (preg_match('/name: ([^,]+)/', $comment, $matches)) {
                    $name = $matches[1];
                } else {
                    $name = $username;
                }

                // Update comment dengan status aktif dan waktu kadaluarsa baru
                $updatedComment = "status: active, name: {$name}, expiry: {$newExpiryTime->format('Y-m-d H:i:s')}";
                $updateUserQuery = (new Query('/ip/hotspot/user/set'))
                    ->equal('.id', $userId)
                    ->equal('comment', $updatedComment);

                // Query untuk update data di MikroTik
                $client->query($updateUserQuery)->read();

                // Update status di voucher_lists
                $updateStatus = DB::table('voucher_lists')
                    ->where('name', $username) // Pastikan menggunakan $username yang sedang diproses
                    ->update(['status' => 'Online']);

                Log::info("Voucher updated for username: {$username}. Rows affected: {$updateStatus}");
            }
        }

        return response()->json([
            'message' => 'Komentar dan waktu kadaluarsa semua pengguna yang sesuai berhasil diperbarui, dan status voucher diperbarui menjadi sudah digunakan.',
        ]);

    } catch (\Exception $e) {
        // Tangani kesalahan dan tampilkan pesan kesalahan
        Log::error("Error in updateAllHotspotUsersByPhoneNumber: {$e->getMessage()}", [
            'exception' => $e,
        ]);
        return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
    }
    }

    public function UpdateData($mikrotikConfig)
{
    try {
        $client = $this->getClientVoucher($mikrotikConfig);

        $hotspotQuery = new Query('/ip/hotspot/user/print');
        $hotspotData = $client->q($hotspotQuery)->read();

        $databaseVouchers = DB::table('voucher_lists')->get();

        $response = [];
        $voucherNamesInHotspot = [];

        foreach ($hotspotData as $user) {
            $username = $user['name'] ?? null;
            $password = $user['password'] ?? null;
            $profile = $user['profile'] ?? null;

            if ($username && $profile !== 'default-trial') {
                $voucherNamesInHotspot[] = $username;

                // Check if the voucher already exists in the database
                $dbVoucher = DB::table('voucher_lists')->where('name', $username)->first();

                // If the user exists in both MikroTik and the database, skip any changes
                if ($dbVoucher && $dbVoucher->name === $username) {
                    continue;  // Skip this iteration, no update needed
                }

                // Update the status to 'Inactive' if user is found in MikroTik but not in the database
                if ($dbVoucher) {
                    DB::table('voucher_lists')
                        ->where('name', $username)
                        ->update(['status' => 'Inactive']);
                }

                $response[] = [
                    'username' => $username,
                    'status' => 'Inactive',
                    'profile' => $profile,
                ];
            }
        }

        // Mark vouchers as 'Already Used' if they exist in the database but not in MikroTik data
        foreach ($databaseVouchers as $voucher) {
            if (!in_array($voucher->name, $voucherNamesInHotspot)) {
                DB::table('voucher_lists')
                    ->where('name', $voucher->name)
                    ->update(['status' => 'Already Used']);
            }
        }

        return response()->json($response, 200);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to fetch hotspot users: ' . $e->getMessage()], 500);
    }
    }

    public function AddVoucher(Request $request)
{
    $request->validate([
        'voucher_hours' => 'required|integer|min:1',
        'voucher_count' => 'required|integer|min:1',
        'profile' => 'required|string',
    ]);

    $voucher_hours = $request->input('voucher_hours');
    $voucher_count = $request->input('voucher_count');
    $profile = $request->input('profile');

    try {
        $client = $this->getClientLogin();

        $profileQuery = (new Query('/ip/hotspot/user/profile/print'))
            ->where('name', $profile);

        $profileResult = $client->query($profileQuery)->read();

        if (empty($profileResult)) {
            return response()->json(['message' => "Profile '$profile' tidak ditemukan."], 404);
        }

        $generatedUsernames = [];

        for ($i = 0; $i < $voucher_count; $i++) {
            $username = strtoupper(Str::random(8));
            $password = $username;
            $expiry_time = now()->addMinutes(1)->format('Y-m-d H:i:s');
            $link_login = "https://hotspot.awh.co.id/login?username={$username}&password={$password}";

            $addUserQuery = (new Query('/ip/hotspot/user/add'))
                ->equal('name', $username)
                ->equal('password', $password)
                ->equal('profile', $profile)
                ->equal('comment', "status: inactive, expiry: $expiry_time");

            $client->query($addUserQuery)->read();

            $generatedUsernames[] = [
                'username' => $username,
                'password' => $password,
                'expiry_time' => $expiry_time,
                'waktu' => $voucher_hours,
                'link_login' => $link_login,
            ];

            DB::table('voucher_lists')->insert([
                'name' => $username,
                'waktu' => $voucher_hours,
                'profile' => $profile,
                'password' => $password,
                'status' => 'Inactive',
                'link_login' => $link_login,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Voucher berhasil dibuat.',
            'generated_vouchers' => $generatedUsernames,
            'note' => 'Ini Link Login jika lupa. Jangan dipakai jika sudah login dan waktu sudah di-extend.',
        ]);
    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
    }

    public function getHotspotUsers($mikrotikConfig)
{
    try {
        $client = $this->getClientVoucher($mikrotikConfig);

        $hotspotQuery = new Query('/ip/hotspot/user/print');
        $hotspotData = $client->q($hotspotQuery)->read();

        $response = [];
        foreach ($hotspotData as $user) {
            $response[] = [
                'username' => $user['name'] ?? 'Not Available',
                'password' => $user['password'] ?? 'Not Available',
                'bytes_in' => $user['bytes-in'] ?? 0,
                'bytes_out' => $user['bytes-out'] ?? 0,
                'comment' => $user['comment'] ?? 'Not Available',
            ];
        }

        return response()->json($response, 200);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to fetch hotspot users: ' . $e->getMessage()], 500);
    }
    }

    public function LoginVoucher(Request $request)
{
    $request->validate([
        'voucher_code' => 'required|string',  // Validasi untuk voucher code
    ]);

    // Ambil semua tenant yang ada dari tabel tenants
    $tenants = Tenant::all();

    $voucher = null;
    $mikrotikConfig = null; // Variabel untuk menyimpan data mikrotik_config

    // Iterasi melalui semua tenant
    foreach ($tenants as $tenant) {
        tenancy()->initialize($tenant);

        $voucher = DB::table('voucher_lists')->where('name', $request->voucher_code)->first();

        $mikrotikConfig = DB::table('mikrotik_config')->first();

        if ($voucher) {
            break;
        }
    }

    if (!$voucher) {
        return response()->json(['message' => 'Invalid voucher in all tenants'], 400);  // Voucher tidak valid
    }

    $this->updateAllHotspotUsersByPhoneNumber($mikrotikConfig);
    $hotspotUsers = $this->getHotspotUsers($mikrotikConfig);

    return response()->json([
        'message' => 'Voucher is valid in tenant: ' . $tenant->id,
        'voucher_code' => $voucher->name,
        'mikrotik_config' => $mikrotikConfig,
        'hotspot_users' => $hotspotUsers
    ]);
    }

    public function DeleteAlltenant(Request $request)
    {
        $tenantId = $request->input('tenant_id');

        $tenants = $tenantId ? Tenant::where('id', $tenantId)->get() : Tenant::all();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $mikrotikConfig = DB::table('mikrotik_config')->first();
            if (!$mikrotikConfig) continue;

            $this->deleteExpiredHotspotUsers($mikrotikConfig);
            $this->UpdateData($mikrotikConfig);
        }

        return response()->json([
            'message' => 'Successfully updated and deleted hotspot users.',
            'processed_tenant_id' => $tenantId ?? 'all'
        ]);
    }


    public function getVoucherLists()
{
    $vouchers = DB::table('voucher_lists')->get();

    return response()->json($vouchers);
    }

    public function setHotspotProfile(Request $request)
    {
        $request->validate([
            'profile_name' => 'required|string|max:255',
            'shared_users' => 'required|integer|min:1',
            'rate_limit' => 'nullable|string',
            'link' => 'nullable|string',
        ]);

        $profile_name = $request->input('profile_name');
        $shared_users = $request->input('shared_users');
        $rate_limit = $request->input('rate_limit');
        $link = $request->input('link');

        try {
             $client = $this->getClient();

            $checkQuery = (new Query('/ip/hotspot/user/profile/print'))
                ->where('name', $profile_name);

            $existingProfiles = $client->query($checkQuery)->read();

            if (!empty($existingProfiles)) {
                $existingLink = DB::table('user_profile_link')
                    ->where('name', $profile_name)
                    ->exists();

                if (!$existingLink) {
                    DB::table('user_profile_link')->insert([
                        'name' => $profile_name,
                        'link' => $link,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return response()->json([
                        'message' => 'Profile sudah ada, tapi link-nya belum ada. Saya tambahin dulu ya'
                    ], 200);
                }

                return response()->json(['message' => 'Profile dan link sudah ada, tidak ada perubahan yang dilakukan'], 200);
            } else {
                $addQuery = (new Query('/ip/hotspot/user/profile/add'))
                    ->equal('name', $profile_name)
                    ->equal('shared-users', $shared_users)
                    ->equal('keepalive-timeout', 'none');

                if (!empty($rate_limit)) {
                    $addQuery->equal('rate-limit', $rate_limit);
                }

                $client->query($addQuery)->read();

                return response()->json(['message' => 'Hotspot profile created successfully'], 201);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
        }
}
