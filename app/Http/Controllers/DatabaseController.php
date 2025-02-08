<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatabaseController extends Controller
{
    function updateVoucher(Request $request)
{
    $request->validate([
        'old_name' => 'required|string',
        'new_name' => 'required|string',
        'status' => 'required|string'
    ]);

    $updated = DB::table('voucher_lists')
        ->where('name', $request->old_name)
        ->update([
            'name' => $request->new_name,
            'status' => $request->status,
            'updated_at' => now()
        ]);

    if ($updated) {
        return response()->json(['message' => 'Voucher updated successfully']);
    } else {
        return response()->json(['message' => 'Voucher not found or not updated'], 404);
    }
}

}
