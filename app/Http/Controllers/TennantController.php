<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;

class TennantController extends Controller
{
    public function index()
    {

        $tenants = Tenant::all();
        return response()->json($tenants);
    }

    public function show($id)
    {
        // Cari tenant berdasarkan ID
        $tenant = Tenant::find($id);
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }
        return response()->json($tenant);
    }

    public function destroy($id)
    {
        // Cari tenant berdasarkan ID
        $tenant = Tenant::find($id);
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        // Hapus tenant
        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted successfully']);
    }

    
}
