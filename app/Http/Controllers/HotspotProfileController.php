<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RouterOS\Query;

class HotspotProfileController extends CentralController
{
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

         $client = $this->getClientLogin();


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

                // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
                // $hotspotController->getHotspotProfile();

                return response()->json([
                    'message' => 'Profile sudah ada, tapi link-nya belum ada. Saya tambahin dulu ya'
                ], 200);
            }

            // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            // $hotspotController->getHotspotUsers1();

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


            DB::table('user_profile_link')->insert([
                'name' => $profile_name,
                'link' => $link,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            // $hotspotController->getHotspotProfile();

            return response()->json(['message' => 'Hotspot profile created successfully'], 201);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotProfile(Request $request)
{
    try {
        $client = $this->getClientLogin();
        $query = new Query('/ip/hotspot/user/profile/print');
        $profiles = $client->query($query)->read();

        if (!empty($profiles)) {
            $result = [];

            foreach ($profiles as $profile) {
                $dbProfile = DB::table('user_profile_link')
                    ->where('name', $profile['name'])
                    ->first();

                $result[] = [
                    'profile_name' => $profile['name'],
                    'shared_users' => $profile['shared-users'] ?? 'Not set',
                    'rate_limit' => $profile['rate-limit'] ?? 'Not set',
                    'link' => $dbProfile->link ?? 'No link available',
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

    public function getHotspotProfileByName(Request $request, $profileName)
    {
        try {

         $client = $this->getClientLogin();

            $query = new Query('/ip/hotspot/user/profile/print');
            $query->where('name', $profileName);

            $profiles = $client->query($query)->read();

            if (!empty($profiles)) {
                $profile = $profiles[0];

                $link = DB::table('user_profile_link')
                    ->where('name', $profileName)
                    ->value('link');

                $result = [
                    'profile_name' => $profile['name'],
                    'shared_users' => $profile['shared-users'] ?? 'Not set',
                    'rate_limit' => $profile['rate-limit'] ?? 'Not set',
                    'link' => $link ?? 'No link found',
                ];

                return response()->json($result, 200);
            } else {
                return response()->json(['message' => 'Profile not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteHotspotProfile($profile_name)
{
    try {

         $client = $this->getClientLogin();

        $checkQuery = (new Query('/ip/hotspot/user/profile/print'))
            ->where('name', $profile_name);

        $profiles = $client->query($checkQuery)->read();


        if (!empty($profiles)) {
            $profile_id = $profiles[0]['.id'];

            $deleteQuery = (new Query('/ip/hotspot/user/profile/remove'))
                ->equal('.id', $profile_id);

            $client->query($deleteQuery)->read();

            // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            // $hotspotController->getHotspotProfile();

            return response()->json(['message' => 'Hotspot profile deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Profile not found'], 404);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function updateHotspotProfile(Request $request, $profile_name)
{    $request->validate([
        'link' => 'required|url',
        'shared_users' => 'nullable|integer',
        'rate_limit' => 'nullable|string',
    ]);

    $link = $request->input('link');
    $shared_users = $request->input('shared_users');
    $rate_limit = $request->input('rate_limit');

    try {

         $client = $this->getClientLogin();

        $checkQuery = (new Query('/ip/hotspot/user/profile/print'))
            ->where('name', $profile_name);

        $profiles = $client->query($checkQuery)->read();

        if (!empty($profiles)) {
            $profile_id = $profiles[0]['.id'];

            $updateQuery = (new Query('/ip/hotspot/user/profile/set'))
                ->equal('.id', $profile_id);

            if ($shared_users) {
                $updateQuery->equal('shared-users', $shared_users);
            }
            if ($rate_limit) {
                $updateQuery->equal('rate-limit', $rate_limit);
            }

            $client->query($updateQuery)->read();

            $existingProfile = DB::table('user_profile_link')
                ->where('name', $profile_name)
                ->first();

            if ($existingProfile) {
                DB::table('user_profile_link')
                    ->where('name', $profile_name)
                    ->update(['link' => $link]);
            } else {
                DB::table('user_profile_link')
                    ->insert([
                        'name' => $profile_name,
                        'link' => $link,
                    ]);
            }

            // $hotspotController = app()->make(\App\Http\Controllers\MqttController::class);
            // $hotspotController->getHotspotProfile();

            return response()->json(['message' => 'Hotspot profile and link updated successfully'], 200);
        } else {
            return response()->json(['message' => 'Profile not found'], 404);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }
}
