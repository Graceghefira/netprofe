<?php

namespace App\Http\Controllers;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\Exceptions\MqttClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class testMqttConnection extends Controller
{
    public function testMqttConnection()
{
    try {
        // Inisiasi koneksi
        $mqtt = MQTT::connection();

        // Coba publish pesan tes ke topik uji
        $mqtt->publish('test/connection', 'Test MQTT connection');

        // Menutup koneksi setelah mengirim pesan
        $mqtt->disconnect();

        echo "Koneksi ke MQTT berhasil!";
    } catch (MqttClientException $e) {
        echo "Gagal menghubungkan ke MQTT: " . $e->getMessage();
    }
}

public function testMqttSubscription()
{
    try {
        $mqtt = MQTT::connection();

        // Subscribe ke topik tertentu
        $mqtt->subscribe('test/connection', function (string $topic, string $message) {
            echo "Pesan diterima: {$message} di topik: {$topic}";
        }, 0);

        // Jalankan loop untuk menunggu pesan
        $mqtt->loop(true);

    } catch (MqttClientException $e) {
        echo "Gagal berlangganan ke MQTT: " . $e->getMessage();
    }
}

public function testMqttConnectionWithLogging()
{
    try {
        $mqtt = MQTT::connection();
        $mqtt->publish('test/connection', 'Test MQTT connection');
        $mqtt->disconnect();

        Log::info('Koneksi MQTT berhasil');
        echo "Koneksi ke MQTT berhasil!";

    } catch (MqttClientException $e) {
        Log::error('Gagal menghubungkan ke MQTT: ' . $e->getMessage());
        echo "Gagal menghubungkan ke MQTT: " . $e->getMessage();
    }
}
}
