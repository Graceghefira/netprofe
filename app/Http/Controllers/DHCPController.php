<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use RouterOS\Query;

class DHCPController extends CentralController
{
    public function addOrUpdateDhcp(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'interface' => 'required|string',
        'lease_time' => 'required|string',
        'address_pool' => 'required|string',
        'add_arp' => 'required|boolean',
    ]);


         $client = $this->getClientLogin();
    try {
        $query = new Query('/ip/dhcp-server/print');
        $dhcpServers = $client->query($query)->read();

        $existingDhcp = collect($dhcpServers)->firstWhere('name', $request->name);

        if ($existingDhcp) {
            $updateQuery = new Query('/ip/dhcp-server/set');
            $updateQuery->equal('numbers', $existingDhcp['.id'])
                ->equal('interface', $request->interface)
                ->equal('lease-time', $request->lease_time)
                ->equal('address-pool', $request->address_pool)
                ->equal('add-arp', $request->add_arp ? 'yes' : 'no');

            $client->query($updateQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'DHCP server updated successfully!',
            ]);
        } else {
            $addQuery = (new Query('/ip/dhcp-server/add'))
                ->equal('name', $request->name)
                ->equal('interface', $request->interface)
                ->equal('lease-time', $request->lease_time)
                ->equal('address-pool', $request->address_pool)
                ->equal('add-arp', $request->add_arp ? 'yes' : 'no');

            $response = $client->query($addQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'DHCP server added successfully!',
                'response' => $response,
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to process DHCP server: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function addOrUpdateNetwork(Request $request)
{

    $request->validate([
        'address' => 'required|string',
        'gateway' => 'required|string',
        'dns_server' => 'required|string',
        'netmask' => 'nullable|integer',
    ]);

    $client = $this->getClientLogin();
    try {

        $query = new Query('/ip/dhcp-server/network/print');
        $networks = $client->query($query)->read();

        $existingNetwork = collect($networks)->firstWhere('address', $request->address);

        if ($existingNetwork) {
            $updateQuery = new Query('/ip/dhcp-server/network/set');
            $updateQuery->equal('numbers', $existingNetwork['.id'])
                ->equal('gateway', $request->gateway)
                ->equal('dns-server', $request->dns_server);

            if (!is_null($request->netmask)) {
                $updateQuery->equal('netmask', (int) $request->netmask);
            }

            $client->query($updateQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'Network updated successfully!'
            ]);
        } else {
            $addQuery = (new Query('/ip/dhcp-server/network/add'))
                ->equal('address', $request->address)
                ->equal('gateway', $request->gateway)
                ->equal('dns-server', $request->dns_server);

            if (!is_null($request->netmask)) {
                $addQuery->equal('netmask', (int) $request->netmask);
            }

            $response = $client->query($addQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'Network added successfully!',
                'response' => $response
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to process network: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function makeLeaseStatic(Request $request)
    {
        $request->validate([
            'address' => 'required|ip',
            'comment' => 'nullable|string',
            'binding_type' => 'nullable|string|in:blocked,bypassed,regular',
            'binding_comment' => 'nullable|string',
        ]);

         $client = $this->getClientLogin();
        try {
            $query = new Query('/ip/dhcp-server/lease/print');
            $leases = $client->query($query)->read();

            $existingLease = collect($leases)->firstWhere('address', $request->input('address'));

            if (!$existingLease) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lease not found for the given address.'
                ], 404);
            }

            $removeQuery = new Query('/ip/dhcp-server/lease/remove');
            $removeQuery->equal('numbers', $existingLease['.id']);
            $client->query($removeQuery)->read();

            $addStaticQuery = new Query('/ip/dhcp-server/lease/add');
            $addStaticQuery->equal('address', $request->input('address'))
                ->equal('mac-address', $existingLease['mac-address'])
                ->equal('server', $existingLease['server'])
                ->equal('comment', $request->input('comment', ''))
                ->equal('disabled', 'no');

            $client->query($addStaticQuery)->read();

            $bindingType = $request->input('binding_type', 'regular');

            $addIpBindingQuery = new Query('/ip/hotspot/ip-binding/add');
            $addIpBindingQuery->equal('mac-address', $existingLease['mac-address'])
                ->equal('address', $request->input('address'))
                ->equal('type', $bindingType)
                ->equal('comment', $request->input('binding_comment', ''));

            $client->query($addIpBindingQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'Lease successfully made static and added to IP binding!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to make lease static or add to IP binding: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getLeases()
    {

         $client = $this->getClientLogin();
        try {
            $query = new Query('/ip/dhcp-server/lease/print');

            $leases = $client->query($query)->read();

            return response()->json([
                'status' => 'success',
                'leases' => $leases
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch leases: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getDhcpServers()
{

         $client = $this->getClientLogin();
    try {
        $dhcpQuery = new Query('/ip/dhcp-server/print');
        $dhcpServers = $client->query($dhcpQuery)->read();

        $interfaceQuery = new Query('/interface/print');
        $interfaces = $client->query($interfaceQuery)->read();

        $usedInterfaces = collect($dhcpServers)->pluck('interface')->all();

        $availableInterfaces = collect($interfaces)->filter(function ($interface) use ($usedInterfaces) {
            return !in_array($interface['name'], $usedInterfaces) && $interface['disabled'] === 'false';
        })->values();

        return response()->json([
            'status' => 'success',
            'dhcpServers' => $dhcpServers,
            'availableInterfaces' => $availableInterfaces
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch data: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function getDhcpServerByName($name)
{

         $client = $this->getClientLogin();
    try {
        $dhcpQuery = new Query('/ip/dhcp-server/print');
        $dhcpQuery->where('name', $name);
        $dhcpServer = $client->query($dhcpQuery)->read();

        if (empty($dhcpServer)) {
            return response()->json([
                'status' => 'error',
                'message' => 'DHCP server not found with name: ' . $name,
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'dhcpServer' => $dhcpServer
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch data: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function getNetworks()
    {
         $client = $this->getClientLogin();
        try {
            $query = new Query('/ip/dhcp-server/network/print');

            $networks = $client->query($query)->read();

            return response()->json([
                'status' => 'success',
                'networks' => $networks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch networks: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getNetworksByGateway($gateway)
{
         $client = $this->getClientLogin();
    try {
        $query = (new Query('/ip/dhcp-server/network/print'))
                    ->where('gateway', $gateway);
        $networks = $client->query($query)->read();

        return response()->json([
            'status' => 'success',
            'networks' => $networks
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to fetch networks by gateway: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function deleteDhcpServerByName($name)
    {
         $client = $this->getClientLogin();
        try {
            $query = new Query('/ip/dhcp-server/print');
            $dhcpServers = $client->query($query)->read();

            $dhcpServer = collect($dhcpServers)->firstWhere('name', $name);

            if (!$dhcpServer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'DHCP server not found with the specified name.',
                ], 404);
            }

            $deleteQuery = new Query('/ip/dhcp-server/remove');
            $deleteQuery->equal('numbers', $dhcpServer['.id']);

            $client->query($deleteQuery)->read();

            return response()->json([
                'status' => 'success',
                'message' => 'DHCP server deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete DHCP server: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteDhcpNetworkByGateway($gateway)
{
         $client = $this->getClientLogin();
    try {
        $query = new Query('/ip/dhcp-server/network/print');
        $dhcpNetworks = $client->query($query)->read();

        $dhcpNetwork = collect($dhcpNetworks)->firstWhere('gateway', $gateway);

        if (!$dhcpNetwork) {
            return response()->json([
                'status' => 'error',
                'message' => 'DHCP network not found with the specified gateway.',
            ], 404);
        }

        $deleteQuery = new Query('/ip/dhcp-server/network/remove');
        $deleteQuery->equal('numbers', $dhcpNetwork['.id']);

        $client->query($deleteQuery)->read();

        return response()->json([
            'status' => 'success',
            'message' => "DHCP network with gateway '{$gateway}' deleted successfully.",
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete DHCP network: ' . $e->getMessage(),
        ], 500);
    }
    }

    public function deleteDhcpLeaseAndIpBindingByAddress($address)
    {
         $client = $this->getClientLogin();
        try {
            $queryLease = new Query('/ip/dhcp-server/lease/print');
            $dhcpLeases = $client->query($queryLease)->read();

            $dhcpLease = collect($dhcpLeases)->firstWhere('address', $address);

            if ($dhcpLease) {
                $deleteLeaseQuery = new Query('/ip/dhcp-server/lease/remove');
                $deleteLeaseQuery->equal('numbers', $dhcpLease['.id']);

                $client->query($deleteLeaseQuery)->read();
            }

            $queryIpBinding = new Query('/ip/hotspot/ip-binding/print');
            $ipBindings = $client->query($queryIpBinding)->read();

            $ipBinding = collect($ipBindings)->firstWhere('address', $address);

            if ($ipBinding) {
                $deleteIpBindingQuery = new Query('/ip/hotspot/ip-binding/remove');
                $deleteIpBindingQuery->equal('numbers', $ipBinding['.id']);

                $client->query($deleteIpBindingQuery)->read();
            }

            return response()->json([
                'status' => 'success',
                'message' => "DHCP lease and IP binding with address '{$address}' deleted successfully.",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete DHCP lease or IP binding: ' . $e->getMessage(),
            ], 500);
        }
    }
}
