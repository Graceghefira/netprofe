<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use RouterOS\Client;
use RouterOS\Query;

class MqttController extends Controller
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

    public function connectToMqtt()
    {
        $server = 'sysnet.awh.co.id';
        $port = 1883;
        $username = 'dhivapos';
        $password = 'FurlaRasaMelon2024';
        $clientId = 'laravel_client_' . uniqid(); // Unique client ID for each connection

        try {
            // Create a new MQTT client instance
            $mqtt = new MqttClient($server, $port, $clientId);

            // Set up connection settings
            $connectionSettings = (new ConnectionSettings)
                ->setUsername($username)
                ->setPassword($password);

            // Connect to the MQTT broker
            $mqtt->connect($connectionSettings, true);

            // Disconnect after connection is successful
            // $mqtt->disconnect();

            // Return success response
            // return response()->json(['status' => 'Connected to MQTT broker successfully']);
            return $mqtt;
        } catch (\PhpMqtt\Client\Exceptions\MqttClientException $e) {
            // Handle connection errors
            return response()->json(['status' => 'Failed to connect to MQTT broker', 'error' => $e->getMessage()], 500);
        }
    }

    public function getHotspotUsers1()
    {
        try {
            $client = $this->getClient();

            // Query to get the list of all hotspot users
            $userQuery = new Query('/ip/hotspot/user/print');
            $users = $client->query($userQuery)->read();

            // Query to get active users
            $activeQuery = new Query('/ip/hotspot/active/print');
            $activeUsers = $client->query($activeQuery)->read();

            // Check if there is already a total bytes in session; if not, set to 0
            $totalBytesIn = session()->get('total_bytes_in', 0);
            $totalBytesOut = session()->get('total_bytes_out', 0);

            // Transform activeUsers into a map for easier access by username
            $activeUsersMap = [];
            foreach ($activeUsers as $activeUser) {
                $username = $activeUser['user'];
                $activeUsersMap[$username] = $activeUser;
            }

            // Process each user to merge active user data and calculate bytes-in and bytes-out
            $modifiedUsers = array_map(function ($user) use (&$totalBytesIn, &$totalBytesOut, $activeUsersMap) {
                $newUser = [];
                foreach ($user as $key => $value) {
                    // Replace .id with id in the key
                    $newKey = str_replace('.id', 'id', $key);
                    $newUser[$newKey] = $value;
                }

                // If the user is active, override bytes-in and bytes-out with active user data
                if (isset($activeUsersMap[$user['name']])) {
                    $activeUser = $activeUsersMap[$user['name']];
                    $newUser['bytes-in'] = isset($activeUser['bytes-in']) ? (int)$activeUser['bytes-in'] : 0;
                    $newUser['bytes-out'] = isset($activeUser['bytes-out']) ? (int)$activeUser['bytes-out'] : 0;
                } else {
                    // If the user is not active, check for previous data in the database
                    $existingUser = DB::table('user_bytes_log')
                        ->where('user_name', $user['name'])
                        ->orderBy('timestamp', 'desc')
                        ->first();

                    // If previous data exists, use it
                    if ($existingUser) {
                        $newUser['bytes-in'] = (int)$existingUser->bytes_in;
                        $newUser['bytes-out'] = (int)$existingUser->bytes_out;
                    } else {
                        // If no previous data, set to 0
                        $newUser['bytes-in'] = 0;
                        $newUser['bytes-out'] = 0;
                    }
                }

                // Add bytes-in and bytes-out to the total
                $totalBytesIn += isset($newUser['bytes-in']) ? (int)$newUser['bytes-in'] : 0;
                $totalBytesOut += isset($newUser['bytes-out']) ? (int)$newUser['bytes-out'] : 0;

                return $newUser;
            }, $users);

            // Calculate total_bytes as the sum of total_bytes_in and total_bytes_out
            $totalBytes = $totalBytesIn + $totalBytesOut;

            // Save the latest total bytes to the session for persistence across requests
            session()->put('total_bytes_in', $totalBytesIn);
            session()->put('total_bytes_out', $totalBytesOut);

            // Prepare message data excluding bytes-in, bytes-out, and total bytes
            $messageData = [
                'total_user' => count($modifiedUsers),
                'users' => $modifiedUsers,
            ];

            // Calculate a hash of the message data excluding bytes-in and bytes-out
            $messageDataWithoutBytes = array_map(function ($user) {
                // Remove bytes-in, bytes-out, and total bytes from the user data before hashing
                unset($user['bytes-in']);
                unset($user['bytes-out']);
                return $user;
            }, $messageData['users']);

            $currentDataHash = md5(json_encode($messageDataWithoutBytes));

            // Retrieve the last data hash from session (or default to null if not set)
            $lastDataHash = session()->get('last_data_hash', null);

            // Check if the data has changed (only proceed if the data hash has changed)
            if ($currentDataHash !== $lastDataHash) {
                // MQTT Publish code using connectToMqtt function
                $mqtt = $this->connectToMqtt();

                // Ensure $mqtt is not null
                if ($mqtt) {
                    // Convert message data to JSON (including total bytes data)
                    $mqttMessage = json_encode([
                        'total_user' => count($modifiedUsers),
                        'total_bytes_in' => $totalBytesIn,
                        'total_bytes_out' => $totalBytesOut,
                        'total_bytes' => $totalBytes,
                        'users' => $modifiedUsers,
                    ]);

                    // Publish message to the topic with retain flag set to true
                    $mqtt->publish('/test_publish', $mqttMessage, 0, true); // QoS 0, Retain true

                    // Disconnect from MQTT broker
                    $mqtt->disconnect();

                    // Update the last data hash in session to reflect the new state
                    session()->put('last_data_hash', $currentDataHash);
                }
            }

            // Return JSON response without pagination
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
