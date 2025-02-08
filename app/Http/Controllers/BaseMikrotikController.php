<?php

namespace App\Http\Controllers;
use RouterOS\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BaseMikrotikController extends Controller
{
    protected function getClient($endpoint = null)
    {
        // Gunakan endpoint default jika tidak diberikan
        $endpoint = $endpoint ?? 'primary';

        Log::info("Using endpoint: $endpoint");

        // Konfigurasi untuk dua endpoint
        $endpoints = [
            'primary' => [
                'host' => '45.149.93.122',
                'user' => 'admin',
                'pass' => 'dhiva1029',
                'port' => 8182,
            ],
            'secondary' => [
                'host' => 'id-37.hostddns.us',
                'user' => 'admin',
                'pass' => 'admin2',
                'port' => 7447,
            ],
            'third' => [
                'host' => 'id-4.hostddns.us',
                'user' => 'admin',
                'pass' => 'admin2',
                'port' => 21326,
            ],
        ];

        // Validasi endpoint
        if (!array_key_exists($endpoint, $endpoints)) {
            throw new \Exception("Invalid endpoint specified: $endpoint");
        }

        // Ambil konfigurasi berdasarkan endpoint
        $config = $endpoints[$endpoint];

        try {
            return new Client($config);
        } catch (\Exception $e) {
            Log::error("Failed to connect to MikroTik API on endpoint $endpoint: " . $e->getMessage());
            throw $e;
        }
    }

    public function checkCurrentEndpoint()
{
    try {
        // Ambil endpoint dari cache, default ke 'primary' jika tidak ditemukan
        $currentEndpoint = Cache::get('global_endpoint');

        return response()->json([
            'message' => 'Current endpoint retrieved successfully.',
            'current_endpoint' => $currentEndpoint,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
}

// public function sendToTelegram($message)
// {
//     try {
//         $token = 'YOUR_TELEGRAM_BOT_TOKEN'; // Masukkan token bot Anda
//         $chatId = 'YOUR_CHAT_ID'; // Masukkan chat ID Anda (atau grup)

//         // URL API Telegram
//         $url = "https://api.telegram.org/bot{$token}/sendMessage";

//         // Kirim pesan ke Telegram
//         $response = Http::post($url, [
//             'chat_id' => $chatId,
//             'text' => $message,
//         ]);

//         // Periksa respons
//         if ($response->successful()) {
//             return response()->json(['message' => 'Message sent to Telegram successfully!']);
//         } else {
//             return response()->json(['error' => 'Failed to send message to Telegram'], 500);
//         }
//     } catch (\Exception $e) {
//         return response()->json(['error' => $e->getMessage()], 500);
//     }
// }


    }


