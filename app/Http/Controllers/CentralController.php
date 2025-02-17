<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use RouterOS\Client;

class CentralController extends Controller
{
    protected function getClient()
    {
        $config = [
            'host' => '45.149.93.122',
            'user' => 'admin',
            'pass' => 'dhiva1029',
            'port' => 8182,
        ];

        return new Client($config);
    }
}
