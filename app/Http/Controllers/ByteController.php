<?php

namespace App\Http\Controllers;

use App\Models\AkunKantor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RouterOS\Query;

class ByteController extends CentralController
{
    public function getHotspotUsers()
{
    try {

         $client = $this->getClientLogin();

        $userQuery = new Query('/ip/hotspot/user/print');
        $users = $client->query($userQuery)->read();

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

          $client = $this->getClientLogin();

        $query = new Query('/ip/hotspot/user/print');
        $query->where('name', $no_hp);

        $users = $client->query($query)->read();

        if (empty($users)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user = $users[0];

        $activeSessionsQuery = (new Query('/ip/hotspot/active/print'))
            ->where('user', $user['name']);

        $activeSessions = $client->query($activeSessionsQuery)->read();

        foreach ($activeSessions as $session) {
            $terminateSessionQuery = (new Query('/ip/hotspot/active/remove'))
                ->equal('.id', $session['.id']);

            $client->query($terminateSessionQuery)->read();
        }

        $deleteQuery = (new Query('/ip/hotspot/user/remove'))->equal('.id', $user['.id']);
        $client->query($deleteQuery)->read();

        if (in_array($user['profile'], ['Owner', 'staff'])) {
            AkunKantor::where('no_hp', $no_hp)->delete();
        }

        // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
        // $hotspotController->getHotspotUsers1();

        return response()->json(['message' => 'Hotspot user deleted successfully']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotProfile(Request $request)
{
    try {
          $client = $this->getClientLogin();

        $profileQuery = new Query('/ip/hotspot/user/profile/print');
        $profiles = $client->query($profileQuery)->read();

        $userQuery = new Query('/ip/hotspot/user/print');
        $users = $client->query($userQuery)->read();
        if (!empty($profiles)) {
            $result = [];

            foreach ($profiles as $profile) {
                $profileName = $profile['name'];
                $result[] = [
                    'profile_name' => $profileName,
                    'shared_users' => $profile['shared-users'] ?? 'Not set',
                    'rate_limit' => $profile['rate-limit'] ?? 'Not set',
                ];
            }

            return response()->json(['profiles' => $result], 200);
        } else {
            return response()->json(['message' => 'No profiles found'], 404);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotUsersByDateRangeWithLoginCheck(Request $request)
{
    try {
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Tanggal awal dan akhir harus disediakan'], 400);
        }


        $startDate = $startDate . ' 00:00:00';
        $endDate = $endDate . ' 23:59:59';

        $usersQuery = DB::table('user_bytes_log')
            ->select(
                'user_name',
                'role',
                DB::raw('SUM(bytes_in) as total_bytes_in'),
                DB::raw('SUM(bytes_out) as total_bytes_out'),
                DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_user_bytes')
            )
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->groupBy('user_name', 'role')
            ->orderBy(DB::raw('SUM(bytes_in) + SUM(bytes_out)'), 'desc');

        $paginatedUsers = $usersQuery->paginate(5);


        $users = $paginatedUsers->items();


        $paginationInfo = [
            'current_page' => $paginatedUsers->currentPage(),
            'last_page' => $paginatedUsers->lastPage(),
            'per_page' => $paginatedUsers->perPage(),
            'total' => $paginatedUsers->total(),
        ];

        $totalBytesIn = $usersQuery->sum('bytes_in');
        $totalBytesOut = $usersQuery->sum('bytes_out');
        $totalBytes = $totalBytesIn + $totalBytesOut;


        return response()->json([
            'users' => $users,
            'pagination' => $paginationInfo,
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
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Tanggal awal dan akhir harus disediakan'], 400);
        }

        $startDate = $startDate . ' 00:00:00';
        $endDate = $endDate . ' 23:59:59';


        $logs = DB::table('user_bytes_log')
            ->select(
                DB::raw('DATE(timestamp) as date'),
                DB::raw('SUM(bytes_in) as total_bytes_in'),
                DB::raw('SUM(bytes_out) as total_bytes_out'),
                DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_bytes')
            )
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(timestamp)'))
            ->orderBy(DB::raw('DATE(timestamp)'), 'asc')
            ->get();


        $uniqueRoles = DB::table('user_bytes_log')
            ->select('role')
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->distinct()
            ->pluck('role');

        foreach ($logs as $log) {
            $largestUser = DB::table('user_bytes_log')
                ->select(
                    'user_name',
                    'role',
                    DB::raw('(bytes_in + bytes_out) as total_user_bytes')
                )
                ->whereDate('timestamp', $log->date)
                ->orderBy('total_user_bytes', 'desc')
                ->first();

            if ($largestUser && $log->total_bytes > 0) {
                $largestUserPercentage = round(($largestUser->total_user_bytes / $log->total_bytes) * 100);
            } else {
                $largestUserPercentage = 0;
            }

                $log->largest_user = [
                'user_name' => $largestUser->user_name,
                'role' => $largestUser->role,
                'percentage' => $largestUserPercentage . "%"
            ];

            $users = DB::table('user_bytes_log')
                ->select(
                    'user_name',
                    'role',
                    DB::raw('SUM(bytes_in) as total_bytes_in'),
                    DB::raw('SUM(bytes_out) as total_bytes_out'),
                    DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_user_bytes')
                )
                ->whereDate('timestamp', $log->date)
                ->groupBy('user_name', 'role')
                ->orderBy('total_user_bytes', 'desc')
                ->get();

            $log->all_users = $users;
        }

        $totalBytesIn = $logs->sum('total_bytes_in');
        $totalBytesOut = $logs->sum('total_bytes_out');
        $totalBytes = $totalBytesIn + $totalBytesOut;

        return response()->json([
            'details' => $logs,
            'total_bytes_in' => $totalBytesIn,
            'total_bytes_out' => $totalBytesOut,
            'total_bytes' => $totalBytes,
            'unique_roles' => $uniqueRoles,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotUsersByUniqueRole(Request $request)
{
    try {
        $role = $request->input('role');
        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');

        if (!$startDate || !$endDate) {
            return response()->json(['error' => 'Parameter harus lengkap'], 400);
        }

        $startDate = $startDate . ' 00:00:00';
        $endDate = $endDate . ' 23:59:59';

        $dbTable = 'user_bytes_log';
        $columnName = 'user_name'; 

        $query = DB::table($dbTable)
            ->select(
                DB::raw('DATE(timestamp) as date'),
                DB::raw('SUM(bytes_in) as total_bytes_in'),
                DB::raw('SUM(bytes_out) as total_bytes_out'),
                DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_bytes')
            )
            ->whereBetween('timestamp', [$startDate, $endDate]);

        if ($role !== "All") {
            $query->where('role', $role);
        }

        $logs = $query->groupBy(DB::raw('DATE(timestamp)'))
            ->orderBy(DB::raw('DATE(timestamp)'), 'asc')
            ->get();

        $totalBytesIn = $logs->sum('total_bytes_in');
        $totalBytesOut = $logs->sum('total_bytes_out');
        $totalBytes = $totalBytesIn + $totalBytesOut;

        foreach ($logs as $log) {
            $largestUserQuery = DB::table($dbTable)
                ->select($columnName, DB::raw('(bytes_in + bytes_out) as total_user_bytes'))
                ->whereDate('timestamp', $log->date);

            if ($role !== "All") {
                $largestUserQuery->where('role', $role);
            }

            $largestUser = $largestUserQuery->orderBy('total_user_bytes', 'desc')->first();

            $largestUserPercentage = ($largestUser && $log->total_bytes > 0) ? round(($largestUser->total_user_bytes / $log->total_bytes) * 100) : 0;

            $log->largest_user = [
                $columnName => $largestUser->$columnName ?? null,
                'percentage' => $largestUserPercentage . "%"
            ];

            $usersQuery = DB::table($dbTable)
                ->select($columnName, DB::raw('SUM(bytes_in) as total_bytes_in'), DB::raw('SUM(bytes_out) as total_bytes_out'), DB::raw('(SUM(bytes_in) + SUM(bytes_out)) as total_user_bytes'))
                ->whereDate('timestamp', $log->date)
                ->groupBy($columnName)
                ->orderBy('total_user_bytes', 'desc');

            if ($role !== "All") {
                $usersQuery->where('role', $role);
            }

            $users = $usersQuery->get();

            $log->all_users = $users;
        }

        $this->logApiUsageBytes();

        return response()->json([
            'details' => $logs,
            'total_bytes_in' => $totalBytesIn,
            'total_bytes_out' => $totalBytesOut,
            'total_bytes' => $totalBytes,
            'role' => $role,
            'dbTable' => $dbTable // Returning table name
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }


    public function logApiUsageBytes()
{
    try {

        $client = $this->getClientLogin();

        $userQuery = new Query('/ip/hotspot/user/print');
        $users = $client->query($userQuery)->read();

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

            // Initialize bytes-in and bytes-out
            $newUser['bytes-in'] = 0;
            $newUser['bytes-out'] = 0;

            // Check if the user is active
            if (isset($activeUsersMap[$user['name']])) {
                $activeUser = $activeUsersMap[$user['name']];
                $newUser['bytes-in'] = isset($activeUser['bytes-in']) ? (int)$activeUser['bytes-in'] : 0;
                $newUser['bytes-out'] = isset($activeUser['bytes-out']) ? (int)$activeUser['bytes-out'] : 0;
            } else {
                // Check if there's an existing log for the user
                $existingUser = DB::table('user_bytes_log')
                    ->where('user_name', $user['name'])
                    ->orderBy('timestamp', 'desc')
                    ->first();

                if ($existingUser) {
                    $newUser['bytes-in'] = (int)$existingUser->bytes_in;
                    $newUser['bytes-out'] = (int)$existingUser->bytes_out;
                }
            }

            // Get the previous log to compare with the current one
            $lastLog = DB::table('user_bytes_log')
                ->where('user_name', $newUser['name'])
                ->orderBy('timestamp', 'desc')
                ->first();

            // Calculate the difference in bytes (only if the new value is larger)
            $bytesInDifference = 0;
            $bytesOutDifference = 0;

            if ($lastLog) {
                // Only calculate the difference if the new value is greater than the previous one
                $bytesInDifference = max(0, $newUser['bytes-in'] - $lastLog->bytes_in); // Only positive change
                $bytesOutDifference = max(0, $newUser['bytes-out'] - $lastLog->bytes_out); // Only positive change
            } else {
                // If no previous log, insert the first log without calculating a difference
                $bytesInDifference = $newUser['bytes-in'];
                $bytesOutDifference = $newUser['bytes-out'];
            }

            // Update total bytes
            $totalBytesIn += $bytesInDifference;
            $totalBytesOut += $bytesOutDifference;

            // Only insert if there's a positive change in bytes
            if ($bytesInDifference > 0 || $bytesOutDifference > 0) {
                DB::table('user_bytes_log')->insert([
                    'user_name' => $newUser['name'],
                    'role' => isset($user['profile']) ? $user['profile'] : 'guest',
                    'bytes_in' => $bytesInDifference,
                    'bytes_out' => $bytesOutDifference,
                    'timestamp' => now(),
                ]);
            }

            return $newUser;
        }, $users);

        $totalBytes = $totalBytesIn + $totalBytesOut;

        // Update session values
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





}
