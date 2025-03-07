<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SetTenantDatabase
{
    public function handle($request, Closure $next)
    {
        // Ambil tenant_id dari header X-Tenant-ID yang terenkripsi
        $encryptedTenantId = $request->header('X-Tenant-ID');

        if (!$encryptedTenantId) {
            return response()->json(['error' => 'Tenant ID is required'], 400);
        }

        // Dekripsi tenant_id
        try {
            $tenantId = Crypt::decryptString($encryptedTenantId);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to decrypt Tenant ID'], 500);
        }

        // Ambil tenant dari database menggunakan tenant_id yang didekripsi
        $tenant = Tenant::where('id', $tenantId)->first();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Inisialisasi database tenant
        tenancy()->initialize($tenant);

        // Menambahkan log untuk memastikan tenant yang digunakan
        Log::info('Using database connection for tenant: ' . $tenant->name);
        Log::info('Tenant database name: ' . $tenant->database_name);

        return $next($request);
    }
}
