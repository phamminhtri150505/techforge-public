<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // Mã đơn giữ thứ tự ổn định khi nhiều đơn được tạo cùng một thời điểm.
        $orders = Order::where('user_id', $request->user()->id)
            ->latest()->orderByDesc('id')->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Request $request, int $order)
    {
        return view('orders.show', ['order' => Order::where('user_id', $request->user()->id)->with('items')->findOrFail($order)]);
    }

    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'recipient' => 'required|string|max:100', 'phone' => ['required', 'regex:/^\+?[0-9]{9,15}$/'],
            'address' => 'required|string|min:10|max:500', 'checkout_token' => 'required|uuid',
            'simulation_ack' => 'accepted', 'accept_warnings' => 'sometimes|accepted',
        ]);
        $existing = Order::where('user_id', $request->user()->id)->where('checkout_token', $data['checkout_token'])->first();
        if ($existing) {
            return to_route('orders.show', $existing);
        }
        $cart = $request->session()->get('cart');
        if (! $cart || ! hash_equals($cart['checkout_token'], $data['checkout_token']) || empty($cart['quote'])) {
            throw ValidationException::withMessages(['cart' => 'Giỏ hàng đã thay đổi hoặc chưa được xem lại. Vui lòng mở giỏ hàng.']);
        }
        $order = $orders->place($request->user(), $cart, $data, $cart['quote']);
        $request->session()->forget('cart');

        return to_route('orders.show', $order)->with('notice', 'Đã tạo đơn COD mô phỏng. Không thu tiền và không giao hàng thực tế.');
    }
}
