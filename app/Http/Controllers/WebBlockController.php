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
        'ip' => 'nullable|ip', // IP yang akan di-unblock
    ]);

    $domain = $request->input('domain');
    $ipInput = $request->input('ip');

    try {
        $client = $this->getClient();

        // Jika IP langsung diberikan
        if ($ipInput) {
            // Hapus dari address list berdasarkan IP
            $findQuery = (new Query('/ip/firewall/address-list/print'))
                ->where('list', 'blocked_sites')
                ->where('address', $ipInput);
            $blockedSites = $client->query($findQuery)->read();

            // Hapus semua entri yang ditemukan
            foreach ($blockedSites as $blockedSite) {
                $id = $blockedSite['.id'];
                $deleteQuery = (new Query('/ip/firewall/address-list/remove'))
                    ->equal('.id', $id);
                $client->query($deleteQuery)->read();
            }

            // Hapus firewall rules yang memblokir berdasarkan IP
            $firewallIPRule = (new Query('/ip/firewall/filter/print'))
                ->where('dst-address', $ipInput);
            $firewallRules = $client->query($firewallIPRule)->read();

            foreach ($firewallRules as $rule) {
                $id = $rule['.id'];
                $deleteFirewallRuleQuery = (new Query('/ip/firewall/filter/remove'))
                    ->equal('.id', $id);
                $client->query($deleteFirewallRuleQuery)->read();
            }

            return response()->json(['message' => 'IP has been unblocked successfully'], 200);
        }

        // Jika domain diberikan, hapus semua entri terkait domain
        if ($domain) {
            // Resolusi DNS untuk mendapatkan semua IP terkait domain
            $dnsRecords = dns_get_record($domain, DNS_A + DNS_AAAA);

            if (!$dnsRecords) {
                return response()->json(['error' => 'Domain resolution failed'], 400);
            }

            // Looping untuk menghapus semua entri terkait dengan domain
            foreach ($dnsRecords as $record) {
                $ipAddress = $record['ip'] ?? $record['ipv6']; // Ambil IPv4 atau IPv6

                // Hapus dari address list berdasarkan IP
                $findQuery = (new Query('/ip/firewall/address-list/print'))
                    ->where('list', 'blocked_sites')
                    ->where('address', $ipAddress);
                $blockedSites = $client->query($findQuery)->read();

                foreach ($blockedSites as $blockedSite) {
                    $id = $blockedSite['.id'];
                    $deleteQuery = (new Query('/ip/firewall/address-list/remove'))
                        ->equal('.id', $id);
                    $client->query($deleteQuery)->read();
                }

                // Hapus firewall rules yang memblokir berdasarkan IP
                $firewallIPRule = (new Query('/ip/firewall/filter/print'))
                    ->where('dst-address', $ipAddress);
                $firewallRules = $client->query($firewallIPRule)->read();

                foreach ($firewallRules as $rule) {
                    $id = $rule['.id'];
                    $deleteFirewallRuleQuery = (new Query('/ip/firewall/filter/remove'))
                        ->equal('.id', $id);
                    $client->query($deleteFirewallRuleQuery)->read();
                }
            }

            // Hapus semua entri yang berkomentar tentang domain dengan pencarian manual
            $blockedSites = $client->query((new Query('/ip/firewall/address-list/print'))
                ->where('list', 'blocked_sites'))
                ->read();

            foreach ($blockedSites as $blockedSite) {
                // Cek apakah key 'comment' ada sebelum memeriksa domain di dalamnya
                if (isset($blockedSite['comment']) && strpos($blockedSite['comment'], $domain) !== false) {
                    $id = $blockedSite['.id'];
                    $deleteQuery = (new Query('/ip/firewall/address-list/remove'))
                        ->equal('.id', $id);
                    $client->query($deleteQuery)->read();
                }
            }

            // Hapus semua aturan firewall yang terkait dengan domain dengan pencarian manual
            $firewallRules = $client->query((new Query('/ip/firewall/filter/print')))
                ->read();

            foreach ($firewallRules as $rule) {
                // Cek apakah key 'comment' ada sebelum memeriksa domain di dalamnya
                if (isset($rule['comment']) && strpos($rule['comment'], $domain) !== false) {
                    $id = $rule['.id'];
                    $deleteFirewallRuleQuery = (new Query('/ip/firewall/filter/remove'))
                        ->equal('.id', $id);
                    $client->query($deleteFirewallRuleQuery)->read();
                }
            }

            return response()->json(['message' => "All entries related to $domain have been unblocked successfully"], 200);
        }

        return response()->json(['error' => 'No valid input provided'], 400);
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
        $request->validate([
            'domain' => 'required|string',
        ]);

        $domain = $request->input('domain');
        $client = $this->getClient();

        try {
            // Menambahkan domain ke dalam address-list Mikrotik
            $addAddressListQuery = (new Query('/ip/firewall/address-list/add'))
                ->equal('list', $domain)
                ->equal('address', $domain)
                ->equal('comment', 'Blocked by Laravel API');

            $this->client->query($addAddressListQuery)->read();

            // Menambahkan firewall filter rule untuk memblokir akses ke domain
            $addFilterRuleQuery = (new Query('/ip/firewall/filter/add'))
                ->equal('chain', 'forward')
                ->equal('protocol', 'tcp')
                ->equal('dst-port', '80,443')
                ->equal('content', $domain)
                ->equal('action', 'drop')
                ->equal('comment', 'Drop traffic to blocked domain'.$domain);

            $this->client->query($addFilterRuleQuery)->read();

              // Menambahkan data ke Layer 7 protocol dengan regex format
            $layer7Regex = '^(Host:\\s*(www.)?' . preg_quote($domain) . ')';
            $addLayer7ProtocolQuery = (new Query('/ip/firewall/layer7-protocol/add'))
            ->equal('name', 'block-' . $domain)
            ->equal('regexp', $layer7Regex)
            ->equal('comment', 'Layer 7 rule for blocking ' . $domain);

            $this->client->query($addLayer7ProtocolQuery)->read();

//             return response()->json(['success' => 'Domain berhasil diblokir dan firewall rule ditambahkan!'], 200);m,n']
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal memblokir domain atau menambahkan firewall rule: ' . $e->getMessage()], 500);
        }
    }


    public function blockWebsite1(Request $request)
{
    $request->validate([
        'domain' => 'required|string', // Input domain oleh user, contoh: facebook.com
    ]);

    // Ambil domain dari input pengguna
    $domain = $request->input('domain');
    $client = $this->getClient();

    try {
        // 1. Menambahkan domain utama ke address-list Mikrotik
        $addAddressListQuery = (new Query('/ip/firewall/address-list/add'))
            ->equal('list', 'blocked-domains')
            ->equal('address', $domain)
            ->equal('comment', 'Blocked by Laravel API for ' . $domain);

        $this->client->query($addAddressListQuery)->read();

        // 2. Menambahkan firewall filter rule untuk memblokir akses ke domain dan subdomain
        $addFilterRuleQuery = (new Query('/ip/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('protocol', 'tcp')
            ->equal('dst-port', '80,443')
            ->equal('content', $domain)
            ->equal('action', 'drop')
            ->equal('comment', 'Drop traffic to blocked domain ' . $domain);

        $this->client->query($addFilterRuleQuery)->read();

        // 3. Menambahkan Layer 7 Protocol Rule dengan wildcard untuk memblokir subdomain secara otomatis
        $layer7Regex = '^(Host:\\s*([a-zA-Z0-9_-]+\\.)*' . preg_quote($domain) . ')';
        $addLayer7ProtocolQuery = (new Query('/ip/firewall/layer7-protocol/add'))
            ->equal('name', 'block-' . $domain)
            ->equal('regexp', $layer7Regex)
            ->equal('comment', 'Layer 7 rule for blocking ' . $domain . ' and its subdomains');

        $this->client->query($addLayer7ProtocolQuery)->read();

        // 4. Menambahkan Static DNS Entry untuk memblokir resolusi DNS domain yang diblokir
        $addStaticDnsEntryQuery = (new Query('/ip/dns/static/add'))
            ->equal('name', $domain)
            ->equal('address', '127.0.0.1') // Alamat IP tidak valid untuk memblokir resolusi DNS
            ->equal('comment', 'DNS block for ' . $domain);

        $this->client->query($addStaticDnsEntryQuery)->read();

        // 5. Membersihkan cache DNS di Mikrotik agar aturan segera berlaku
        $clearDnsCacheQuery = (new Query('/ip/dns/cache/flush'));
        $this->client->query($clearDnsCacheQuery)->read();

        return response()->json(['success' => 'Domain ' . $domain . ' dan semua subdomainnya berhasil diblokir!'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Gagal memblokir domain atau menambahkan firewall rule: ' . $e->getMessage()], 500);
    }
}



    protected $signature = 'mikrotik:update-blocked-ips';
    protected $description = 'Update blocked IPs for domains in MikroTik';

    public function updateBlockedIPs()
{
    try {
        $client = $this->getClient();

        // Mendapatkan daftar domain yang diblokir dari MikroTik
        $getQuery = (new Query('/ip/firewall/address-list/print'))
            ->where('list', 'blocked_sites'); // Mengambil daftar dari blocked_sites

        $blockedSites = $client->query($getQuery)->read();

        // Ambil semua domain dari daftar blokir
        $domains = [];
        $now = new \DateTime(); // Waktu saat ini untuk membandingkan creation-time
        foreach ($blockedSites as $site) {
            if (isset($site['comment']) && strpos($site['comment'], 'Blocked: ') !== false) {
                $domain = str_replace('Blocked: ', '', $site['comment']);
                $domains[$domain][] = $site['address']; // Menyimpan IP terkait domain

            }
        }

        // Memperbarui daftar IP untuk setiap domain
        foreach ($domains as $domain => $existingIPs) {
            // Mengambil IP terbaru untuk domain melalui DNS
            $dnsRecords = dns_get_record($domain, DNS_A + DNS_AAAA);

            foreach ($dnsRecords as $record) {
                $newIpAddress = $record['ip'] ?? $record['ipv6'] ?? null;

                // Jika IP tidak kosong dan belum ada di daftar
                if ($newIpAddress && !in_array($newIpAddress, $existingIPs)) {
                    // Tambahkan IP baru ke address list 'blocked_sites'
                    $addQuery = (new Query('/ip/firewall/address-list/add'))
                        ->equal('list', 'blocked_sites')
                        ->equal('address', $newIpAddress)
                        ->equal('comment', "Blocked: $domain");
                    $client->query($addQuery)->read();

                    // Log informasi tentang IP baru yang ditambahkan
                    Log::info("Added new IP $newIpAddress for domain $domain to blocked_sites.");
                }
            }
        }

        // Menambahkan pesan ke log Laravel
        Log::info('Blocked IPs updated successfully via WebBlockController.');
        return response()->json(['message' => 'Blocked IPs updated successfully.'], 200);

    } catch (\Exception $e) {
        // Log error jika terjadi masalah
        Log::error('Error updating blocked IPs in WebBlockController: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to update blocked IPs.'], 500);
    }
    }


}
