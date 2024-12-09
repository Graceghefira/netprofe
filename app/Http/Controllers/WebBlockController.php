<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Query;
use illuminate\Console\Command;

class WebBlockController extends Controller
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

    protected $client;

    public function __construct()
    {
    $this->client = $this->getClient(); // Pastikan ini memanggil metode yang menginisialisasi client
    }

    public function blockDomain(Request $request)
{
    // Validasi domain
    $request->validate([
        'domain' => 'required|string|max:255',
    ]);

    $domain = $request->input('domain');  // Mengambil nama domain dari request

    try {
        // Menstripping domain (menghapus prefix https:// atau http:// jika ada)
        $strippedDomain = preg_replace('/^https?:\/\//', '', $domain);
        $strippedDomain = rtrim($strippedDomain, '/');  // Menghapus slash di akhir

        $client = $this->getClient();  // Mendapatkan client MikroTik

        // **Mengambil DNS records (A dan AAAA) dari domain**
        $dnsRecords = dns_get_record($strippedDomain, DNS_A | DNS_AAAA);
        if (empty($dnsRecords)) {
            return response()->json(['error' => 'Domain resolution failed'], 400);
        }

        // Menambahkan IP ke address list MikroTik jika belum ada
        foreach ($dnsRecords as $record) {
            $ipAddress = $record['ip'] ?? $record['ipv6'];

            if ($ipAddress) {
                // Cek apakah IP sudah ada dalam address list
                $existingRecord = $client->query('/ip/firewall/address-list/print', [
                    '?address' => $ipAddress,
                    '?list' => 'blocked_sites',
                ])->read();

                if (empty($existingRecord)) {
                    // Menambahkan IP ke address-list MikroTik jika belum ada
                    $addQuery = (new Query('/ip/firewall/address-list/add'))
                        ->equal('list', 'blocked_sites')
                        ->equal('address', $ipAddress)
                        ->equal('comment', "Blocked: $strippedDomain ($ipAddress)");
                    $client->query($addQuery)->read();

                    // Menambahkan aturan firewall untuk memblokir IP yang ditemukan
                    $firewallRule = (new Query('/ip/firewall/filter/add'))
                        ->equal('chain', 'forward')
                        ->equal('dst-address', $ipAddress)
                        ->equal('action', 'drop')
                        ->equal('comment', "Block IP $ipAddress for $strippedDomain");
                    $client->query($firewallRule)->read();
                }
            }
        }

        // Membuat regex dinamis untuk domain dan subdomain yang diberikan
        $l7Pattern = "^.+(" . preg_quote($strippedDomain, '/') . "|cdn\." . preg_quote($strippedDomain, '/') . "|.{1}" . preg_quote($strippedDomain, '/') . "|.*\." . preg_quote($strippedDomain, '/') . ").*$";

        // Menambahkan Layer 7 Protocol di MikroTik
        $addL7Protocol = (new Query('/ip/firewall/layer7-protocol/add'))
            ->equal('name', "block_$strippedDomain")
            ->equal('regexp', $l7Pattern);
        $client->query($addL7Protocol)->read();

        // Menambahkan aturan firewall untuk memblokir HTTP (port 80) berdasarkan Layer 7 protocol
        $firewallL7RuleTcp = (new Query('/ip/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('protocol', 'tcp')
            ->equal('dst-port', '80')
            ->equal('layer7-protocol', "block_$strippedDomain")
            ->equal('action', 'drop')
            ->equal('comment', "Block HTTP traffic via Layer 7 for $strippedDomain");
        $client->query($firewallL7RuleTcp)->read();

        // Menambahkan aturan firewall untuk memblokir HTTPS (port 443) berdasarkan Layer 7 protocol
        $firewallL7RuleHttps = (new Query('/ip/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('protocol', 'tcp')
            ->equal('dst-port', '443')
            ->equal('layer7-protocol', "block_$strippedDomain")
            ->equal('action', 'drop')
            ->equal('comment', "Block HTTPS traffic via Layer 7 for $strippedDomain");
        $client->query($firewallL7RuleHttps)->read();

        // Kembalikan pesan sukses
        return response()->json(['message' => "$domain blocked permanently (IP-based blocking and Layer 7 filtering)"], 200);

    } catch (\Exception $e) {
        // Menangani error dan memberikan pesan yang jelas
        return response()->json(['error' => 'Failed to block domain: ' . $e->getMessage()], 500);
    }
    }

    public function unblockDomain(Request $request)
    {
        // Validasi domain
        $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $domain = $request->input('domain');  // Mengambil nama domain dari request

        try {
            // Menstripping domain (menghapus prefix https:// atau http:// jika ada)
            $strippedDomain = preg_replace('/^https?:\/\//', '', $domain);
            $strippedDomain = rtrim($strippedDomain, '/');  // Menghapus slash di akhir

            $client = $this->getClient();  // Mendapatkan client MikroTik

            // **Mengambil DNS records (A dan AAAA) dari domain**
            $dnsRecords = dns_get_record($strippedDomain, DNS_A | DNS_AAAA);
            if (empty($dnsRecords)) {
                return response()->json(['error' => 'Domain resolution failed'], 400);
            }

            // Menghapus IP dari address-list dan aturan firewall berdasarkan DNS records
            foreach ($dnsRecords as $record) {
                $ipAddress = $record['ip'] ?? $record['ipv6'];

                if ($ipAddress) {
                    // Menghapus IP dari address-list MikroTik
                    $removeQuery = (new Query('/ip/firewall/address-list/remove'))
                        ->equal('list', 'blocked_sites')
                        ->equal('address', $ipAddress);
                    $client->query($removeQuery)->read();

                    // Menghapus aturan firewall yang memblokir IP tersebut
                    $removeFirewallRule = (new Query('/ip/firewall/filter/remove'))
                        ->equal('dst-address', $ipAddress)
                        ->equal('comment', "Block IP $ipAddress for $strippedDomain");
                    $client->query($removeFirewallRule)->read();
                }
            }

            // Menghapus Layer 7 Protocol berdasarkan domain yang diberikan
            $removeL7Protocol = (new Query('/ip/firewall/layer7-protocol/remove'))
                ->equal('name', "block_$strippedDomain");
            $client->query($removeL7Protocol)->read();

            // Menghapus aturan firewall untuk HTTP (port 80) berdasarkan Layer 7 protocol
            $removeFirewallL7RuleTcp = (new Query('/ip/firewall/filter/remove'))
                ->equal('chain', 'forward')
                ->equal('protocol', 'tcp')
                ->equal('dst-port', '80')
                ->equal('layer7-protocol', "block_$strippedDomain")
                ->equal('comment', "Block HTTP traffic via Layer 7 for $strippedDomain");
            $client->query($removeFirewallL7RuleTcp)->read();

            // Menghapus aturan firewall untuk HTTPS (port 443) berdasarkan Layer 7 protocol
            $removeFirewallL7RuleHttps = (new Query('/ip/firewall/filter/remove'))
                ->equal('chain', 'forward')
                ->equal('protocol', 'tcp')
                ->equal('dst-port', '443')
                ->equal('layer7-protocol', "block_$strippedDomain")
                ->equal('comment', "Block HTTPS traffic via Layer 7 for $strippedDomain");
            $client->query($removeFirewallL7RuleHttps)->read();

            // Kembalikan pesan sukses
            return response()->json(['message' => "$domain unblocked successfully (IP-based and Layer 7 filtering removed)"], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}

