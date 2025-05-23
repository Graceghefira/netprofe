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
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'menu_id' => 'required|exists:menus,id',
    ]);

    $menu = Menu::find($request->input('menu_id'));

    $expiry_time = Carbon::now()->addMinutes($menu->expiry_duration);

    $order = new Order();
    $order->user_id = $request->input('user_id');
    $order->menu_id = $menu->id;
    $order->expiry_time = $expiry_time;
    $order->save();

    return response()->json(['message' => 'Order created successfully', 'expiry_time' => $expiry_time]);
}

    public function addMenu(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'expiry_time' => 'required|integer|min:1',
        ]);

        try {
            $menu = new Menu();
            $menu->name = $request->input('name');
            $menu->price = $request->input('price');
            $menu->expiry_time = $request->input('expiry_time');
            $menu->save();

            return response()->json(['message' => 'Menu added successfully', 'menu' => $menu], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function editMenu(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'expiry_time' => 'sometimes|required|integer|min:1',
        ]);

        try {
            $menu = Menu::findOrFail($id);

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
            $menus = Menu::all();

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
            $orders = Order::all();

            if ($orders->isEmpty()) {
                return response()->json(['message' => 'No orders found'], 404);
            }

            return response()->json(['orders' => $orders]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
