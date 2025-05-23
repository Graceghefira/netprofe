<?php

namespace App\Http\Controllers;

use App\Models\MikrotikConfig;
use Illuminate\Http\Request;
use RouterOS\Client;

class CentralController extends Controller
{
    protected function getClient()
    {
        $config = [
            'host' => '45.149.93.122',
            'user' => 'netpro',
            'pass' => 'netpro',
            'port' => 8736,
        ];

        return new Client($config);
    }

    protected function getClientLogin()
    {
        $config = MikrotikConfig::first();

        if (!$config) {
            throw new \Exception('Konfigurasi Mikrotik tidak ditemukan untuk tenant ini!');
        }

        // Initialize RouterOS Client
        return new Client([
            'host' => $config->host,
            'user' => $config->user,
            'pass' => $config->pass,
            'port' => $config->port,
        ]);
    }

    protected function getClientVoucher($mikrotikConfig)
{
    if (!$mikrotikConfig) {
        throw new \Exception('Konfigurasi Mikrotik tidak ditemukan untuk tenant ini!');
    }

    return new Client([
        'host' => $mikrotikConfig->host,
        'user' => $mikrotikConfig->user,
        'pass' => $mikrotikConfig->pass,
        'port' => $mikrotikConfig->port,
    ]);
    }

    public function index()
    {
        $configs = MikrotikConfig::all();
        return response()->json($configs);
    }


    public function store(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'user' => 'required|string',
            'pass' => 'required|string',
            'port' => 'required|integer',
        ]);

        $config = MikrotikConfig::create($request->all());

        return response()->json([
            'message' => 'Konfigurasi berhasil ditambahkan!',
            'data' => $config
        ], 201);
    }


    public function show($id)
    {
        $config = MikrotikConfig::find($id);

        if (!$config) {
            return response()->json(['message' => 'Konfigurasi tidak ditemukan!'], 404);
        }

        return response()->json($config);
    }


    public function update(Request $request, $id)
    {
        $config = MikrotikConfig::find($id);

        if (!$config) {
            return response()->json(['message' => 'Konfigurasi tidak ditemukan!'], 404);
        }

        $request->validate([
            'host' => 'sometimes|required|string',
            'user' => 'sometimes|required|string',
            'pass' => 'sometimes|required|string',
            'port' => 'sometimes|required|integer',
        ]);

        $config->update($request->all());

        return response()->json([
            'message' => 'Konfigurasi berhasil diperbarui!',
            'data' => $config
        ]);
    }


    public function destroy($id)
    {
        $config = MikrotikConfig::find($id);

        if (!$config) {
            return response()->json(['message' => 'Konfigurasi tidak ditemukan!'], 404);
        }

        $config->delete();

        return response()->json(['message' => 'Konfigurasi berhasil dihapus!']);
    }

}
