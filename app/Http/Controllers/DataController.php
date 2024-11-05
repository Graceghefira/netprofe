<?php

namespace App\Http\Controllers;

use App\Events\DataUpdated;
use Illuminate\Http\Request;

class DataController extends Controller
{
    public function updateData(Request $request)
    {
        $data = ['message' => 'Data terbaru']; // Data yang ingin dikirim

        // Kirim event saat data diperbarui
        DataUpdated::dispatch($data);

        return response()->json(['status' => 'success']);
    }
}
