<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function createOrder(Request $request)
{
    // Validasi input
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'menu_id' => 'required|exists:menus,id',
    ]);

    // Ambil menu yang dipesan
    $menu = Menu::find($request->input('menu_id'));

    // Hitung waktu kadaluwarsa berdasarkan waktu sekarang + expiry_duration dari menu
    $expiry_time = Carbon::now()->addMinutes($menu->expiry_duration);

    // Simpan pesanan
    $order = new Order();
    $order->user_id = $request->input('user_id');
    $order->menu_id = $menu->id;
    $order->expiry_time = $expiry_time;
    $order->save();

    return response()->json(['message' => 'Order created successfully', 'expiry_time' => $expiry_time]);
}

    public function addMenu(Request $request)
    {
        // Validasi input
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'expiry_time' => 'required|integer|min:1', // Assuming expiry_time is in minutes
        ]);

        
        try {
            // Membuat menu baru
            $menu = new Menu();
            $menu->name = $request->input('name');
            $menu->price = $request->input('price');
            $menu->expiry_time = $request->input('expiry_time'); // menyimpan dalam menit
            $menu->save();

            return response()->json(['message' => 'Menu added successfully', 'menu' => $menu], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function editMenu(Request $request, $id)
    {
        // Validasi input
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'expiry_time' => 'sometimes|required|integer|min:1', // Assuming expiry_time is in minutes
        ]);

        try {
            // Cari menu berdasarkan ID
            $menu = Menu::findOrFail($id);

            // Update menu berdasarkan input yang ada
            if ($request->has('name')) {
                $menu->name = $request->input('name');
            }
            if ($request->has('price')) {
                $menu->price = $request->input('price');
            }
            if ($request->has('expiry_time')) {
                $menu->expiry_time = $request->input('expiry_time');
            }

            $menu->save();

            return response()->json(['message' => 'Menu updated successfully', 'menu' => $menu]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAllMenus()
    {
        try {
            // Retrieve all menus from the database
            $menus = Menu::all();

            // Check if there are menus
            if ($menus->isEmpty()) {
                return response()->json(['message' => 'No menus found'], 404);
            }

            return response()->json(['menus' => $menus]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAllOrders()
    {
        try {
            // Retrieve all orders from the database
            $orders = Order::all();

            // Check if there are orders
            if ($orders->isEmpty()) {
                return response()->json(['message' => 'No orders found'], 404);
            }

            return response()->json(['orders' => $orders]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
