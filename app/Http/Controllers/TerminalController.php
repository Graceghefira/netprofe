<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use RouterOS\Query;

class TerminalController extends CentralController
{
    public function executeMikrotikCommand(Request $request)
{
    try {
        $command = $request->input('command');

        $client = $this->getClientLogin();

        $query = new Query($command);

        $response = $client->q($query)->read();

        return response()->json(['result' => $response], 200);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Mikrotik command execution failed: ' . $e->getMessage()], 500);
    }
        }

    public function executeCmdCommand(Request $request)
{
    try {
        $command = $request->input('command');
        $output = [];
        $return_var = 0;

        set_time_limit(30);
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            return response()->json(['error' => 'Command execution failed: ' . implode("\n", $output)], 500);
        }

        return response()->json(['result' => $output], 200);

    } catch (\Exception $e) {
        return response()->json(['error' => 'CMD command execution failed: ' . $e->getMessage()], 500);
    }
        }

    public function getRouterInfo()
        {
            try {
                $client = $this->getClientLogin();

                $resourceQuery = new Query('/system/resource/print');
                $resourceData = $client->q($resourceQuery)->read();

                $timeQuery = new Query('/system/clock/print');
                $timeData = $client->q($timeQuery)->read();

                $voltage = isset($resourceData[0]['voltage']) ? $resourceData[0]['voltage'] . 'V' : 'Not Available';
                $temperature = isset($resourceData[0]['temperature']) ? $resourceData[0]['temperature'] . 'C' : 'Not Available';

                $response = [
                    'time' => $timeData[0]['time'] ?? null,
                    'date' => $timeData[0]['date'] ?? null,
                    'uptime' => $resourceData[0]['uptime'] ?? null,
                    'cpu_load' => $resourceData[0]['cpu-load'] . '%' ?? null,
                    'free_memory' => round($resourceData[0]['free-memory'] / (1024 * 1024), 1) . ' MiB' ?? null,
                    'total_memory' => round($resourceData[0]['total-memory'] / (1024 * 1024), 1) . ' MiB' ?? null,
                    'free_hdd' => round($resourceData[0]['free-hdd-space'] / (1024 * 1024), 1) . ' MiB' ?? null,
                    'total_hdd' => round($resourceData[0]['total-hdd-space'] / (1024 * 1024), 1) . ' MiB' ?? null,
                    'sector_writes' => $resourceData[0]['write-sect-since-reboot'] ?? null,
                    'bad_blocks' => isset($resourceData[0]['bad-blocks']) ? $resourceData[0]['bad-blocks'] . '%' : '0%',
                    'cpu_architecture' => $resourceData[0]['architecture-name'] ?? null,
                    'board_name' => $resourceData[0]['board-name'] ?? null,
                    'router_os' => $resourceData[0]['version'] ?? null,
                    'build_time' => $resourceData[0]['build-time'] ?? 'Not Available',
                    'factory_software' => $resourceData[0]['factory-software'] ?? 'Not Available',
                ];

                return response()->json($response, 200);

            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to fetch router info: ' . $e->getMessage()], 500);
            }
        }

}
