<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\BuilderService;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function store(Request $request, CartService $carts)
    {
        $cart = $carts->fromBuilder($request->session()->get('builder.parts', []), $request->session()->get('builder.owned', []));
        $carts->quote($cart);
        $request->session()->put('cart', $cart + ['checkout_token' => (string) Str::uuid()]);

        return to_route('cart.index')->with('notice', 'Đã thay giỏ bằng phần cần mua của phương án Builder. Linh kiện đã có chỉ dùng để kiểm tra.');
    }

    public function addProduct(Request $request, Product $product, CartService $carts)
    {
        abort_unless($product->is_active, 404);
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:10']);
        $cart = $request->session()->get('cart', ['parts' => [], 'owned' => [], 'items' => []]);
        if (! isset($cart['items'])) {
            $cart = $carts->fromBuilder($cart['parts'], []);
        }
        $cart['items'][$product->id] = ($cart['items'][$product->id] ?? 0) + (int) $data['quantity'];
        $carts->quote($cart);
        $cart['checkout_token'] = (string) Str::uuid();
        unset($cart['quote']);
        $request->session()->put('cart', $cart);

        return to_route('cart.index')->with('notice', 'Đã thêm linh kiện vào giỏ. Các dòng mua lẻ bổ sung không được dùng để kết luận tương thích cả PC.');
    }

    public function index(Request $request, CartService $carts)
    {
        $cart = $request->session()->get('cart');
        $build = null;
        $issues = [];
        if ($cart) {
            if (! isset($cart['items'])) {
                $cart = $carts->fromBuilder($cart['parts'], $cart['owned'] ?? []) + ['checkout_token' => $cart['checkout_token']];
                $request->session()->put('cart', $cart);
            }
            try {
                $build = $carts->quote($cart);
                $quote = $carts->fingerprint($build);
                $previousQuote = $request->session()->get('cart.quote');
                // Tab khác xem giá/thông số mới không có nghĩa là người dùng đã
                // đồng ý trên biểu mẫu cũ. Chỉ đổi mã khi báo giá thực sự đổi.
                if (! is_string($previousQuote) || ! hash_equals($previousQuote, $quote)) {
                    $cart['checkout_token'] = (string) Str::uuid();
                    $request->session()->put('cart.checkout_token', $cart['checkout_token']);
                }
                $request->session()->put('cart.quote', $quote);
            } catch (ValidationException $exception) {
                $request->session()->forget('cart.quote');
                $issues = $exception->errors();
            }
        }

        return view('cart.index', ['build' => $build, 'issues' => $issues, 'cart' => $cart,
            'repairProducts' => $cart && $build === null
                ? Product::whereIn('id', array_keys($cart['items'] ?? []))->get()->keyBy('id') : collect(),
            'token' => $cart['checkout_token'] ?? null]);
    }

    public function updateLine(Request $request, int $product)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:10']);
        $cart = $request->session()->get('cart');
        abort_unless($cart && isset($cart['items'][$product]), 404);
        $quantity = (int) $data['quantity'];
        if ($quantity === 0) {
            unset($cart['items'][$product]);
            // Bỏ linh kiện thuộc phương án thì chuyển giỏ sang mua lẻ; không giữ báo cáo cũ.
            if (in_array($product, array_values($cart['parts']), true)) {
                $cart['parts'] = [];
                $cart['owned'] = [];
            }
        } else {
            $cart['items'][$product] = $quantity;
        }
        if (! $cart['items']) {
            $request->session()->forget('cart');
        } else {
            unset($cart['quote']);
            $cart['checkout_token'] = (string) Str::uuid();
            $request->session()->put('cart', $cart);
        }

        return to_route('cart.index')->with('notice', 'Đã cập nhật giỏ. Nếu bỏ linh kiện của phương án Builder, giỏ chuyển sang mua lẻ và ngừng đánh giá cả PC.');
    }

    public function destroy(Request $request)
    {
        $request->session()->forget('cart');

        return to_route('cart.index')->with('notice', 'Đã bỏ các linh kiện khỏi giỏ.');
    }

    public function editInBuilder(Request $request, BuilderService $builder)
    {
        $parts = $request->session()->get('cart.parts', []);
        abort_unless($parts, 422, 'Giỏ mua lẻ không đại diện một phương án Builder.');
        $builder->build($parts);
        $request->session()->put('builder.parts', $parts);
        $request->session()->put('builder.owned', $request->session()->get('cart.owned', []));
        $request->session()->forget('builder.assistant');

        return to_route('builder.index')->with('notice', 'Đã mở phương án trong Builder. Khi thêm lại sẽ thay giỏ hiện tại, kể cả các dòng mua lẻ bổ sung.');
    }
}
