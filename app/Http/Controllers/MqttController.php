<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use RouterOS\Client;
use RouterOS\Query;


class MqttController extends BaseMikrotikController
{


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
        $endpoint = Cache::get('global_endpoint');

        // Dapatkan client berdasarkan endpoint
        $client = $this->getClient($endpoint);

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
                // Define the topic based on the endpoint (prefix logic based on more than 3 cases)
                switch ($endpoint) {
                    case 'primary':
                        $topicSuffix = 'Netpro';
                        break;
                    case 'secondary':
                        $topicSuffix = 'imago';
                        break;
                    case 'third':
                        $topicSuffix = 'Wow';
                        break;
                    default:
                        $topicSuffix = 'default';  // for any unknown endpoint, use default
                        break;
                }
                $topic = $topicSuffix . '/hotspot-user'; // Prefix is dynamic based on endpoint

                // Convert message data to JSON (including total bytes data)
                $mqttMessage = json_encode([
                    'total_user' => count($modifiedUsers),
                    'total_bytes_in' => $totalBytesIn,
                    'total_bytes_out' => $totalBytesOut,
                    'total_bytes' => $totalBytes,
                    'users' => $modifiedUsers,
                ]);

                // Publish message to the dynamic topic
                $mqtt->publish($topic, $mqttMessage, 0, retain: true); // QoS 0, Retain true

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

    public function getHotspotProfile()
    {
        try {
            // Koneksi ke MikroTik
            $endpoint = Cache::get('global_endpoint', 'primary'); // Default ke 'primary'

            // Dapatkan client berdasarkan endpoint
            $client = $this->getClient($endpoint);

            // Query untuk mendapatkan semua profil Hotspot dari MikroTik
            $query = new Query('/ip/hotspot/user/profile/print');

            // Eksekusi query
            $profiles = $client->query($query)->read();

            // Inisialisasi cache untuk menyimpan data sebelumnya
            static $previousProfiles = null;

            if (!empty($profiles)) {
                $result = [];

                // Ambil semua data dari tabel user_profile_link
                $links = DB::table('user_profile_link')->get()->pluck('link', 'name')->toArray();

                // Loop melalui setiap profil di MikroTik
                foreach ($profiles as $profile) {
                    $profileName = $profile['name'];

                    // Ambil link dari database jika ada, jika tidak gunakan default
                    $link = $links[$profileName] ?? 'No link available';

                    $result[] = [
                        'profile_name' => $profileName,
                        'shared_users' => $profile['shared-users'] ?? 'Not set',
                        'rate_limit' => $profile['rate-limit'] ?? 'Not set',
                        'link' => $link, // Tambahkan link dari database
                    ];
                }

                // Hitung hash dari data yang sudah diproses untuk mendeteksi perubahan
                $currentDataHash = md5(json_encode($result));

                // Ambil hash terakhir dari sesi untuk membandingkan
                $lastDataHash = session()->get('last_profile_data_hash', null);

                // Jika data berubah, kirim ke MQTT
                if ($currentDataHash !== $lastDataHash) {
                    // MQTT Publish code menggunakan connectToMqtt function
                    $mqtt = $this->connectToMqtt();

                    // Pastikan $mqtt tidak null
                    if ($mqtt) {
                        // Tentukan topik berdasarkan endpoint
                        switch ($endpoint) {
                            case 'primary':
                                $topicSuffix = 'Netpro';
                                break;
                            case 'secondary':
                                $topicSuffix = 'imago';
                                break;
                            case 'third':
                                $topicSuffix = 'Wow';
                                break;
                            default:
                                $topicSuffix = 'default'; // Untuk endpoint yang tidak dikenal
                                break;
                        }
                        $topic = $topicSuffix . '/hotspot-user-profile'; // Topik untuk user profile

                        // Konversi data profil ke JSON
                        $mqttMessage = json_encode([
                            'profiles' => $result,
                        ]);

                        // Publish pesan ke topik
                        $mqtt->publish($topic, $mqttMessage, 0, retain: true); // QoS 0, Retain true

                        // Disconnect dari MQTT broker
                        $mqtt->disconnect();

                        // Update hash terakhir ke sesi
                        session()->put('last_profile_data_hash', $currentDataHash);
                    }
                }

                // Return JSON response hanya untuk user profile
                return response()->json([
                    'profiles' => $result,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getRoutes()
{
    try {
        // Retrieve the global endpoint from cache or use a default value
        $endpoint = Cache::get('global_endpoint', 'primary'); // Default to 'primary'

        // Get the MikroTik client based on the endpoint
        $client = $this->getClient($endpoint);

        // Query to fetch all routes from MikroTik
        $routeQuery = new Query('/ip/route/print');
        $routes = $client->query($routeQuery)->read();

        // Initialize previous routes cache
        static $previousRoutes = null;

        if (!empty($routes)) {
            // Only keep the necessary fields (dst.Address, gateway, inactive, active, connect)
            $filteredRoutes = array_map(function ($route) {
                return [
                    'dst.Address' => $route['dst-address'] ?? null, // dst.Address
                    'gateway' => $route['gateway'] ?? null,         // gateway
                    'inactive' => $route['inactive'] ?? null,       // inactive
                    'active' => $route['active'] ?? null,           // active
                    'connect' => $route['connect'] ?? null,         // connect
                ];
            }, $routes);

            // Calculate hash of the current data for change detection
            $currentDataHash = md5(json_encode($filteredRoutes));

            // Retrieve the last data hash from the session for comparison
            $lastDataHash = session()->get('last_route_data_hash', null);

            // If the data has changed, publish to MQTT
            if ($currentDataHash !== $lastDataHash) {
                // MQTT Publish code using connectToMqtt function
                $mqtt = $this->connectToMqtt();

                // Ensure MQTT connection is not null
                if ($mqtt) {
                    // Define the MQTT topic based on the endpoint
                    switch ($endpoint) {
                        case 'primary':
                            $topicSuffix = 'Netpro';
                            break;
                        case 'secondary':
                            $topicSuffix = 'imago';
                            break;
                        case 'third':
                            $topicSuffix = 'Wow';
                            break;
                        default:
                            $topicSuffix = 'default'; // For unrecognized endpoints
                            break;
                    }

                    $topic = $topicSuffix . '/fail-over'; // Topic for the routes

                    // Convert the filtered route data into JSON
                    $mqttMessage = json_encode([
                        'routes' => $filteredRoutes,
                    ]);

                    // Publish the data to MQTT
                    $mqtt->publish($topic, $mqttMessage, 0, retain: true); // QoS 0, Retain true

                    // Disconnect from the MQTT broker
                    $mqtt->disconnect();

                    // Update the session with the new hash to track changes
                    session()->put('last_route_data_hash', $currentDataHash);
                }
            }

            // Return the filtered route data in JSON response
            return response()->json([
                'total_routes' => count($filteredRoutes),
                'routes' => $filteredRoutes,
            ]);
        }

        // Return response if no routes are available
        return response()->json(['message' => 'No routes found'], 404);

    } catch (\Exception $e) {
        // Handle exceptions and return error response
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getNetwatch()
    {
        try {
            // Retrieve the global endpoint from cache or use a default value
            $endpoint = Cache::get('global_endpoint', 'primary'); // Default to 'primary'

            // Get the MikroTik client based on the endpoint
            $client = $this->getClient($endpoint);

            // Query to fetch all netwatch entries from MikroTik
            $netwatchQuery = new Query('/tool/netwatch/print');
            $netwatchEntries = $client->query($netwatchQuery)->read();

            // Initialize previous netwatch cache
            static $previousNetwatch = null;

            if (!empty($netwatchEntries)) {
                // Filter relevant fields from the Netwatch entries
                $filteredNetwatch = array_map(function ($entry) {
                    return [
                        'host' => $entry['host'] ?? null, // Monitored host
                        'status' => $entry['status'] ?? null, // Status (up/down)
                        'since' => $entry['since'] ?? null, // Since when the status changed
                        'timeout' => $entry['timeout'] ?? null, // Timeout period
                        'interval' => $entry['interval'] ?? null, // Check interval
                    ];
                }, $netwatchEntries);

                // Calculate hash of the current data for change detection
                $currentDataHash = md5(json_encode($filteredNetwatch));

                // Retrieve the last data hash from the session for comparison
                $lastDataHash = session()->get('last_netwatch_data_hash', null);

                // If the data has changed, publish to MQTT
                if ($currentDataHash !== $lastDataHash) {
                    // MQTT Publish code using connectToMqtt function
                    $mqtt = $this->connectToMqtt();

                    // Ensure MQTT connection is not null
                    if ($mqtt) {
                        // Define the MQTT topic based on the endpoint
                        switch ($endpoint) {
                            case 'primary':
                                $topicSuffix = 'Netpro';
                                break;
                            case 'secondary':
                                $topicSuffix = 'imago';
                                break;
                            case 'third':
                                $topicSuffix = 'Wow';
                                break;
                            default:
                                $topicSuffix = 'default'; // For unrecognized endpoints
                                break;
                        }

                        $topic = $topicSuffix . '/netwatch-status'; // Topic for the Netwatch

                        // Convert the filtered Netwatch data into JSON
                        $mqttMessage = json_encode([
                            'netwatch' => $filteredNetwatch,
                        ]);

                        // Publish the data to MQTT
                        $mqtt->publish($topic, $mqttMessage, 0, retain: true); // QoS 0, Retain true

                        // Disconnect from the MQTT broker
                        $mqtt->disconnect();

                        // Update the session with the new hash to track changes
                        session()->put('last_netwatch_data_hash', $currentDataHash);
                    }
                }

                // Return the filtered Netwatch data in JSON response
                return response()->json([
                    'total_netwatch' => count($filteredNetwatch),
                    'netwatch' => $filteredNetwatch,
                ]);
            }

            // Return response if no Netwatch entries are available
            return response()->json(['message' => 'No netwatch entries found'], 404);

        } catch (\Exception $e) {
            // Handle exceptions and return error response
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }




}
