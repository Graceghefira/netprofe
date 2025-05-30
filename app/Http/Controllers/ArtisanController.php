<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ArtisanController extends Controller
{
    public function runMigrations()
{
    try {
        Artisan::call('migrate');
        return response()->json(['message' => 'Migrasi berhasil dijalankan.']);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Gagal menjalankan migrasi.', 'error' => $e->getMessage()], 500);
    }
    }

    public function runrollback()
{
    try {
        Artisan::call('migrate:reset');
        return response()->json(['message' => 'rollback berhasil dijalankan.']);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Gagal menjalankan migrasi.', 'error' => $e->getMessage()], 500);
    }
    }

    public function runTenantMigrations()
{
    try {
        Artisan::call('tenants:migrate');

        return response()->json([
            'message' => 'Migrasi tenant berhasil dijalankan.',
            'output' => Artisan::output(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Gagal menjalankan migrasi tenant.',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}
