<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;



class ResponController extends Controller
{
    // public function getPesan(Request $request, $chatId)
    // {
    //     $client = new Client(env('WHATSAPP_API_KEY'), env('WHATSAPP_API_SECRET'));
    //     $messages = $client->messages()->index($chatId);

    //     foreach ($messages as $message) {
    //         echo "Pesan dari: " . $message->getFrom() . "\n";
    //         echo "Pesan: " . $message->getText() . "\n\n";
    //     }
    // }

    public function addTicket(Request $request)
{
    // Validasi input
    $request->validate([
        'hari_masuk' => 'required|date',
        'waktu_masuk' => 'required|date_format:H:i',
        'hari_respon' => 'nullable|date',
        'waktu_respon' => 'nullable|date_format:H:i',
        'nama_admin' => 'nullable|string|max:255',
        'email' => 'required|email|max:255',
        'category' => 'required|string|max:255',
        'priority' => 'required|string|in:low,medium,high,critical',
        'status' => 'required|string|max:255',
        'subject' => 'required|string|max:255',
        'detail_kendala' => 'required|string',
        'owner' => 'required|string|max:255',
        'time_worked' => 'nullable|integer|min:0',
        'due_date' => 'nullable|date',
        'kategori_masalah' => 'nullable|string|max:255',
        'respon_diberikan' => 'nullable|string',
    ]);

    try {
        // Mengenerate tracking ID
        $tanggal = Carbon::now()->format('Ymd');
        $abjad = chr(rand(65, 90)); // Huruf besar A-Z
        $nomorAcak = rand(100, 999);
        $trackingId = substr($tanggal, 2) . $abjad . $nomorAcak;
        $trackingId = substr($trackingId, 0, 9); // Batasi panjang menjadi 9 karakter
        $trackingId = implode('-', str_split($trackingId, 3)); // Tambahkan strip di setiap 3 karakter

        // Membuat tiket baru
        $ticket = new Ticket();
        $ticket->tracking_id = $trackingId;
        $ticket->hari_masuk = $request->input('hari_masuk');
        $ticket->waktu_masuk = $request->input('waktu_masuk');
        $ticket->hari_respon = $request->input('hari_respon');
        $ticket->waktu_respon = $request->input('waktu_respon');
        $ticket->nama_admin = $request->input('nama_admin');
        $ticket->email = $request->input('email');
        $ticket->category = $request->input('category');
        $ticket->priority = $request->input('priority');
        $ticket->status = $request->input('status');
        $ticket->subject = $request->input('subject');
        $ticket->detail_kendala = $request->input('detail_kendala');
        $ticket->owner = $request->input('owner');
        $ticket->time_worked = $request->input('time_worked');
        $ticket->due_date = $request->input('due_date');
        $ticket->kategori_masalah = $request->input('kategori_masalah');
        $ticket->respon_diberikan = $request->input('respon_diberikan');
        $ticket->save();

        return response()->json(['message' => 'Ticket added successfully', 'ticket' => $ticket], 201);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }

    public function updateStatus(Request $request, $tracking_id)
    {
        // Validasi input
        $request->validate([
            'status' => 'required|string|max:255', // Validasi status
        ]);

        try {
            // Cari tiket berdasarkan tracking_id
            $ticket = Ticket::where('tracking_id', $tracking_id)->first();

            // Periksa apakah tiket ditemukan
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            // Update status
            $ticket->status = $request->input('status');
            $ticket->save();

            // Berikan respons sukses
            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'ticket' => $ticket,
            ], 200);
        } catch (\Exception $e) {
            // Respons jika terjadi error
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllTickets(Request $request)
{
    try {
        // Ambil semua data dari tabel tickets
        $tickets = Ticket::all();

        // Respons JSON
        return response()->json([
            'success' => true,
            'message' => 'All tickets retrieved successfully',
            'data' => $tickets,
        ], 200);
    } catch (\Exception $e) {
        // Respons jika terjadi error
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
    }

    public function deleteTicket(Request $request,$tracking_id)
{
    try {
        // Cari tiket berdasarkan tracking_id
        $ticket = Ticket::where('tracking_id', $tracking_id)->first();

        // Periksa apakah tiket ditemukan
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Hapus tiket
        $ticket->delete();

        // Berikan respons sukses
        return response()->json([
            'success' => true,
            'message' => 'Ticket deleted successfully',
        ], 200);
    } catch (\Exception $e) {
        // Respons jika terjadi error
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
    }

    public function getTicketsByDateRange(Request $request)
{
    // Validasi input dari request body
    $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
    ]);

    try {
        // Ambil tiket dalam rentang tanggal
        $tickets = Ticket::whereBetween('hari_masuk', [
            $request->input('start_date'),
            $request->input('end_date')
        ])->get();

        // Jika tidak ada tiket ditemukan
        if ($tickets->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No tickets found in the specified date range',
                'data' => []
            ], 200);
        }

        // Respons JSON
        return response()->json([
            'success' => true,
            'message' => 'Tickets retrieved successfully',
            'data' => $tickets,
            'total' => $tickets->count(),
            'date_range' => [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date')
            ]
        ], 200);
    } catch (\Exception $e) {
        // Respons jika terjadi error
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
    }

}
