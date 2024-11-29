<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Query;
use illuminate\Console\Command;
use Exception;

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

    public function getBlockedWebsites()
{
    try {
        // Koneksi ke MikroTik
        $client = $this->getClient();

        // Query untuk mengambil semua alamat yang ada di address list
        $getQuery = (new Query('/ip/firewall/address-list/print'));

        // Eksekusi query dan baca hasilnya
        $addressList = $client->query($getQuery)->read();

        // Memformat hasil sebagai array
        $allEntries = [];
        foreach ($addressList as $entry) {
            $allEntries[] = [
                'ip' => $entry['address'],    // IP yang ada di address list
                'list' => isset($entry['list']) ? $entry['list'] : null, // Nama dari list, jika ada
                'comment' => isset($entry['comment']) ? $entry['comment'] : null // Komentar, jika ada
            ];
        }

        return response()->json(['address_list' => $allEntries], 200);
    } catch (\Exception $e) {
        // Tangani error jika ada
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }


    public function unblockWebsite(Request $request)
{
    // Validasi input
    $request->validate([
        'domain' => 'nullable|string|max:255', // Domain yang akan di-unblock
    ]);

    $domain = $request->input('domain');

    try {
        $client = $this->getClient();

        if (!$domain) {
            return response()->json(['error' => 'Domain is required to unblock'], 400);
        }

        // 1. Hapus dari address-list berdasarkan domain
        $blockedSites = $client->query((new Query('/ip/firewall/address-list/print'))
            ->where('list', 'blocked-domains'))
            ->read();

        foreach ($blockedSites as $blockedSite) {
            if (isset($blockedSite['comment']) && strpos($blockedSite['comment'], $domain) !== false) {
                $id = $blockedSite['.id'];
                $deleteQuery = (new Query('/ip/firewall/address-list/remove'))
                    ->equal('.id', $id);
                $client->query($deleteQuery)->read();
            }
        }

        // 2. Hapus firewall filter rules yang terkait dengan domain
        $firewallRules = $client->query((new Query('/ip/firewall/filter/print')))
            ->read();

        foreach ($firewallRules as $rule) {
            if (isset($rule['comment']) && strpos($rule['comment'], $domain) !== false) {
                $id = $rule['.id'];
                $deleteFirewallRuleQuery = (new Query('/ip/firewall/filter/remove'))
                    ->equal('.id', $id);
                $client->query($deleteFirewallRuleQuery)->read();
            }
        }

        // 3. Hapus Layer 7 Protocol rules terkait domain
        $layer7Rules = $client->query((new Query('/ip/firewall/layer7-protocol/print')))
            ->read();

        foreach ($layer7Rules as $rule) {
            if (isset($rule['comment']) && strpos($rule['comment'], $domain) !== false) {
                $id = $rule['.id'];
                $deleteLayer7Query = (new Query('/ip/firewall/layer7-protocol/remove'))
                    ->equal('.id', $id);
                $client->query($deleteLayer7Query)->read();
            }
        }

        // 4. Hapus Static DNS Entries terkait domain
        $dnsEntries = $client->query((new Query('/ip/dns/static/print')))
            ->read();

        foreach ($dnsEntries as $entry) {
            if (isset($entry['name']) && ($entry['name'] === $domain || strpos($entry['name'], $domain) !== false)) {
                $id = $entry['.id'];
                $deleteDnsEntryQuery = (new Query('/ip/dns/static/remove'))
                    ->equal('.id', $id);
                $client->query($deleteDnsEntryQuery)->read();
            }
        }

        // 5. Hapus NAT redirect rules terkait DNS
        $natRules = $client->query((new Query('/ip/firewall/nat/print')))
            ->read();

        foreach ($natRules as $rule) {
            if (isset($rule['comment']) && strpos($rule['comment'], 'Redirect DNS to Mikrotik') !== false) {
                $id = $rule['.id'];
                $deleteNatRuleQuery = (new Query('/ip/firewall/nat/remove'))
                    ->equal('.id', $id);
                $client->query($deleteNatRuleQuery)->read();
            }
        }

        // 6. Hapus Raw rules terkait domain (HTTPS/SNI block)
        $rawRules = $client->query((new Query('/ip/firewall/raw/print')))
            ->read();

        foreach ($rawRules as $rule) {
            if (isset($rule['comment']) && strpos($rule['comment'], $domain) !== false) {
                $id = $rule['.id'];
                $deleteRawRuleQuery = (new Query('/ip/firewall/raw/remove'))
                    ->equal('.id', $id);
                $client->query($deleteRawRuleQuery)->read();
            }
        }

        // 7. Flush DNS cache di Mikrotik agar aturan yang tersisa segera dihapus
        $clearDnsCacheQuery = (new Query('/ip/dns/cache/flush'));
        $client->query($clearDnsCacheQuery)->read();

        return response()->json(['success' => "All entries related to $domain have been unblocked successfully"], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function unblockWebsite1(Request $request, $domain = null)
{
    // Validasi domain yang diberikan
    if (!$domain) {
        return response()->json(['error' => 'Domain is required'], 400);
    }

    try {
        $client = $this->getClient();

        // Strip protocol if included in domain
        $strippedDomain = preg_replace('/^https?:\/\//', '', $domain);
        $strippedDomain = rtrim($strippedDomain, '/');

        // 1. Hapus dari Address List yang mengandung komentar atau address dengan domain
        $blockedSites = $client->query((new Query('/ip/firewall/address-list/print')))->read();

        foreach ($blockedSites as $blockedSite) {
            if ((isset($blockedSite['comment']) && stripos($blockedSite['comment'], $strippedDomain) !== false) ||
                (isset($blockedSite['address']) && stripos($blockedSite['address'], $strippedDomain) !== false)) {
                $id = $blockedSite['.id'];
                Log::info('Deleting blocked site from address list with comment or address: ' . $blockedSite['comment'] . ' | ' . $blockedSite['address']);
                $deleteQuery = (new Query('/ip/firewall/address-list/remove'))
                    ->equal('.id', $id);
                $client->query($deleteQuery)->read();
            }
        }

        // 2. Hapus Firewall Rules yang mengandung komentar atau address dengan domain
        $firewallRules = $client->query((new Query('/ip/firewall/filter/print')))->read();

        foreach ($firewallRules as $rule) {
            if ((isset($rule['comment']) && stripos($rule['comment'], $strippedDomain) !== false) ||
                (isset($rule['dst-address']) && stripos($rule['dst-address'], $strippedDomain) !== false)) {
                $id = $rule['.id'];
                Log::info('Deleting firewall rule with comment or address: ' . $rule['comment'] . ' | ' . $rule['dst-address']);
                $deleteQuery = (new Query('/ip/firewall/filter/remove'))
                    ->equal('.id', $id);
                $client->query($deleteQuery)->read();
            }
        }

        // 3. Hapus Layer 7 Protocol Rules yang mengandung nama domain
        $layer7Rules = $client->query((new Query('/ip/firewall/layer7-protocol/print')))->read();

        foreach ($layer7Rules as $rule) {
            if (isset($rule['name']) && stripos($rule['name'], $strippedDomain) !== false) {
                $id = $rule['.id'];
                Log::info('Deleting Layer 7 Protocol rule with name: ' . $rule['name']);
                $deleteQuery = (new Query('/ip/firewall/layer7-protocol/remove'))
                    ->equal('.id', $id);
                $client->query($deleteQuery)->read();
            }
        }

        // 4. Hapus semua Scheduler yang mengandung komentar dengan domain
        $schedules = $client->query((new Query('/system/scheduler/print')))->read();

        foreach ($schedules as $schedule) {
            if (isset($schedule['comment']) && stripos($schedule['comment'], $strippedDomain) !== false) {
                $id = $schedule['.id'];
                Log::info('Deleting scheduler with comment: ' . $schedule['comment']);
                $deleteQuery = (new Query('/system/scheduler/remove'))
                    ->equal('.id', $id);
                $client->query($deleteQuery)->read();
            }
        }

        // 5. Hapus Dynamic IP Blocking Script dari Scheduler
        $dynamicUpdateScheduler = $client->query((new Query('/system/scheduler/print')))->read();

        foreach ($dynamicUpdateScheduler as $schedule) {
            if (isset($schedule['name']) && stripos($schedule['name'], "update_blocked_$strippedDomain") !== false) {
                $id = $schedule['.id'];
                Log::info('Deleting dynamic update scheduler for domain: ' . $strippedDomain);
                $deleteQuery = (new Query('/system/scheduler/remove'))
                    ->equal('.id', $id);
                $client->query($deleteQuery)->read();
            }
        }

        return response()->json(['message' => "All entries related to $domain have been unblocked successfully"], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function blockWebsite(Request $request)
    {
        // Validasi input domain
        $request->validate([
            'domain' => 'required|string',  // Input domain oleh user
        ]);

        // Ambil domain dari input pengguna
        $domain = $request->input('domain');

        try {
            // Membuat koneksi ke MikroTik dengan menggunakan getClient()
            $client = $this->getClient();

            // 1. Tambahkan domain ke address-list MikroTik
            $addAddressListQuery = new Query('/ip/firewall/address-list/add');
            $addAddressListQuery->equal('list', 'blocked-domains')
                                ->equal('address', $domain)
                                ->equal('comment', 'Blocked by Laravel API for ' . $domain);
            $client->query($addAddressListQuery)->read();

            // 2. Tambahkan firewall filter untuk memblokir domain
            $addFilterRuleQuery = new Query('/ip/firewall/filter/add');
            $addFilterRuleQuery->equal('chain', 'forward')
                               ->equal('src-address-list', 'blocked-domains')
                               ->equal('action', 'drop')
                               ->equal('comment', 'Drop traffic to blocked domain ' . $domain);
            $client->query($addFilterRuleQuery)->read();

            // 3. Tambahkan Layer 7 Protocol untuk memblokir domain dan subdomain dengan regex baru
            $escapedDomain = preg_quote($domain, '/');
            $layer7Regex = '^.+(instagram.com|cdninstagram.com|.cdninstagram.com|.instagram.com|instagram.|.instagram|.cdninstagram|cdninstagram.).*$';

            $addLayer7ProtocolQuery = new Query('/ip/firewall/layer7-protocol/add');
            $addLayer7ProtocolQuery->equal('name', 'block-' . $domain)
                                   ->equal('regexp', $layer7Regex)
                                   ->equal('comment', 'Layer 7 rule for blocking ' . $domain . ' and its subdomains');
            $client->query($addLayer7ProtocolQuery)->read();

            // 4. Tambahkan static DNS entry untuk domain yang diblokir
            $addStaticDnsEntryQuery = new Query('/ip/dns/static/add');
            $addStaticDnsEntryQuery->equal('name', $domain)
                                   ->equal('address', '127.0.0.1')  // IP invalid untuk blokir
                                   ->equal('comment', 'DNS block for ' . $domain);
            $client->query($addStaticDnsEntryQuery)->read();

            // 5. Tambahkan wildcard untuk subdomain
            $addStaticDnsEntryWildcardQuery = new Query('/ip/dns/static/add');
            $addStaticDnsEntryWildcardQuery->equal('name', '*.' . $domain)
                                           ->equal('address', '127.0.0.1')
                                           ->equal('comment', 'DNS block for subdomains of ' . $domain);
            $client->query($addStaticDnsEntryWildcardQuery)->read();

            // 6. Redirect semua query DNS ke MikroTik
            $addNatDnsRedirectQuery = new Query('/ip/firewall/nat/add');
            $addNatDnsRedirectQuery->equal('chain', 'dstnat')
                                   ->equal('protocol', 'udp')
                                   ->equal('dst-port', '53')
                                   ->equal('action', 'redirect')
                                   ->equal('to-ports', '53')
                                   ->equal('comment', 'Redirect DNS queries to MikroTik');
            $client->query($addNatDnsRedirectQuery)->read();

            // 7. Block HTTPS menggunakan TLS SNI
            $addRawHttpsBlockQuery = new Query('/ip/firewall/raw/add');
            $addRawHttpsBlockQuery->equal('action', 'drop')
                                  ->equal('chain', 'prerouting')
                                  ->equal('tls-host', $domain)
                                  ->equal('comment', 'Block HTTPS traffic to ' . $domain . ' via SNI');
            $client->query($addRawHttpsBlockQuery)->read();

            // 8. Clear DNS Cache untuk menerapkan perubahan
            $clearDnsCacheQuery = new Query('/ip/dns/cache/flush');
            $client->query($clearDnsCacheQuery)->read();

            // Kembalikan respons sukses
            return response()->json(['success' => 'Domain ' . $domain . ' dan semua subdomainnya berhasil diblokir!'], 200);

        } catch (Exception $e) {
            return response()->json(['error' => 'Gagal memblokir domain atau menambahkan firewall rule: ' . $e->getMessage()], 500);
        }
    }



}
