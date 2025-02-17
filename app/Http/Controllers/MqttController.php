<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use RouterOS\Query;

class MqttController extends CentralController
{
    public function connectToMqtt()
{
    $server = 'sysnet.awh.co.id';
    $port = 1883;
    $username = 'dhivapos';
    $password = 'FurlaRasaMelon2024';
    $clientId = 'laravel_client_' . uniqid();

    try {
        $mqtt = new MqttClient($server, $port, $clientId);

        $connectionSettings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password);

        $mqtt->connect($connectionSettings, true);

        // $mqtt->disconnect();

        // return response()->json(['status' => 'Connected to MQTT broker successfully']);
        return $mqtt;
    } catch (\PhpMqtt\Client\Exceptions\MqttClientException $e) {
        return response()->json(['status' => 'Failed to connect to MQTT broker', 'error' => $e->getMessage()], 500);
    }
    }

    public function getHotspotUsers1()
{
    try {

        $client = $this->getClient();

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

        $messageData = [
            'total_user' => count($modifiedUsers),
            'users' => $modifiedUsers,
        ];

        $messageDataWithoutBytes = array_map(function ($user) {
            unset($user['bytes-in']);
            unset($user['bytes-out']);
            return $user;
        }, $messageData['users']);

        $currentDataHash = md5(json_encode($messageDataWithoutBytes));

        $lastDataHash = session()->get('last_data_hash', null);

        if ($currentDataHash !== $lastDataHash) {
            $mqtt = $this->connectToMqtt();

            if ($mqtt) {
                switch ('first') {
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
                        $topicSuffix = 'default';
                        break;
                }
                $topic = $topicSuffix . '/hotspot-user';

                $mqttMessage = json_encode([
                'total_user' => count($modifiedUsers),
                    'total_bytes_in' => $totalBytesIn,
                    'total_bytes_out' => $totalBytesOut,
                    'total_bytes' => $totalBytes,
                    'users' => $modifiedUsers,
                ]);

                $mqtt->publish($topic, $mqttMessage, 0, retain: true);


                $mqtt->disconnect();

                session()->put('last_data_hash', $currentDataHash);
            }
        }

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

            $client = $this->getClient();

            $query = new Query('/ip/hotspot/user/profile/print');

            $profiles = $client->query($query)->read();

            static $previousProfiles = null;

            if (!empty($profiles)) {
                $result = [];

                $links = DB::table('user_profile_link')->get()->pluck('link', 'name')->toArray();

                foreach ($profiles as $profile) {
                    $profileName = $profile['name'];

                    $link = $links[$profileName] ?? 'No link available';

                    $result[] = [
                        'profile_name' => $profileName,
                        'shared_users' => $profile['shared-users'] ?? 'Not set',
                        'rate_limit' => $profile['rate-limit'] ?? 'Not set',
                        'link' => $link,
                    ];
                }

                $currentDataHash = md5(json_encode($result));

                $lastDataHash = session()->get('last_profile_data_hash', null);

                if ($currentDataHash !== $lastDataHash) {
                    $mqtt = $this->connectToMqtt();

                    if ($mqtt) {
                        switch ('first') {
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
                                $topicSuffix = 'default';
                                break;
                        }
                        $topic = $topicSuffix . '/hotspot-user-profile';

                        $mqttMessage = json_encode([
                            'profiles' => $result,
                        ]);

                        $mqtt->publish($topic, $mqttMessage, 0, retain: true);

                        $mqtt->disconnect();

                        session()->put('last_profile_data_hash', $currentDataHash);
                    }
                }

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

        $client = $this->getClient();

        $routeQuery = new Query('/ip/route/print');
        $routes = $client->query($routeQuery)->read();

        static $previousRoutes = null;

        if (!empty($routes)) {
            $filteredRoutes = array_map(function ($route) {
                return [
                    'dst.Address' => $route['dst-address'] ?? null,
                    'gateway' => $route['gateway'] ?? null,
                    'inactive' => $route['inactive'] ?? null,
                    'active' => $route['active'] ?? null,
                    'connect' => $route['connect'] ?? null,
                ];
            }, $routes);

            $currentDataHash = md5(json_encode($filteredRoutes));

            $lastDataHash = session()->get('last_route_data_hash', null);

            if ($currentDataHash !== $lastDataHash) {
                $mqtt = $this->connectToMqtt();

                if ($mqtt) {
                    switch ('first') {
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
                            $topicSuffix = 'default';
                            break;
                    }

                    $topic = $topicSuffix . '/fail-over';

                    $mqttMessage = json_encode([
                        'routes' => $filteredRoutes,
                    ]);

                    $mqtt->publish($topic, $mqttMessage, 0, retain: true);
                    $mqtt->disconnect();

                    session()->put('last_route_data_hash', $currentDataHash);
                }
            }

            return response()->json([
                'total_routes' => count($filteredRoutes),
                'routes' => $filteredRoutes,
            ]);
        }

        return response()->json(['message' => 'No routes found'], 404);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function getNetwatch()
    {
        try {
            $client = $this->getClient();

            $netwatchQuery = new Query('/tool/netwatch/print');
            $netwatchEntries = $client->query($netwatchQuery)->read();

            static $previousNetwatch = null;

            if (!empty($netwatchEntries)) {
                $filteredNetwatch = array_map(function ($entry) {
                    return [
                        'host' => $entry['host'] ?? null,
                        'status' => $entry['status'] ?? null,
                        'since' => $entry['since'] ?? null,
                        'timeout' => $entry['timeout'] ?? null,
                        'interval' => $entry['interval'] ?? null,
                    ];
                }, $netwatchEntries);

                $currentDataHash = md5(json_encode($filteredNetwatch));

                $lastDataHash = session()->get('last_netwatch_data_hash', null);

                if ($currentDataHash !== $lastDataHash) {
                    $mqtt = $this->connectToMqtt();

                    if ($mqtt) {
                        switch ('first') {
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
                                $topicSuffix = 'default';
                                break;
                        }

                        $topic = $topicSuffix . '/netwatch-status';
                        $mqttMessage = json_encode([
                            'netwatch' => $filteredNetwatch,
                        ]);

                        $mqtt->publish($topic, $mqttMessage, 0, retain: true);

                        $mqtt->disconnect();

                        session()->put('last_netwatch_data_hash', $currentDataHash);
                    }
                }

                return response()->json([
                    'total_netwatch' => count($filteredNetwatch),
                    'netwatch' => $filteredNetwatch,
                ]);
            }

            return response()->json(['message' => 'No netwatch entries found'], 404);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
