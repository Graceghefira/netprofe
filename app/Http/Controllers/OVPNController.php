<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use RouterOS\Client;

class OVPNController extends AnnualController
{
    protected function getClient()
    {
        $config = [
            'host' => '45.149.93.122',
            'user' => 'netpro',
            'pass' => 'netpro',
            'port' => 8084,
        ];

        return new Client($config);
    }

    public function checkConnection()
    {
        $client = $this->getClient();

        if ($client instanceof Client) {
            return response()->json(['message' => 'Koneksi ke MikroTik berhasil!']);
        } else {
            return response()->json(['error' => 'Gagal terhubung ke MikroTik.'], 500);
        }
    }

    public function createVPN(){

    }
}
