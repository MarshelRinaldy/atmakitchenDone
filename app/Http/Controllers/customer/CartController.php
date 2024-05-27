<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Dukpro;
use App\Models\Hampers;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PromoPoint;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class CartController extends Controller
{
    public function addToCart(Request $request)
    {
        $productId = $request->input('product_id');
        $hamperId = $request->input('hamper_id');

        if ($productId) {
            $product = Dukpro::find($productId);

            if (!$product) {
                return redirect()->back()->with('error', 'Product not found.');
            }

            $cart = Session::get('cart', []);

            if (isset($cart['products'][$productId])) {
                $cart['products'][$productId]['quantity']++;
            } else {
                $cart['products'][$productId] = [
                    "id" => $product->id, // Add product ID to cart item for easier identification
                    "nama" => $product->nama,
                    "quantity" => 1,
                    "harga" => $product->harga,
                    "image" => $product->image
                ];
            }

            Session::put('cart', $cart);

            return redirect()->back()->with('success', 'Product added to cart successfully!');
        }

        if ($hamperId) {
            $hamper = Hampers::find($hamperId);

            if (!$hamper) {
                return redirect()->back()->with('error', 'Hamper not found.');
            }

            $cart = Session::get('cart', []);

            if (isset($cart['hampers'][$hamperId])) {
                $cart['hampers'][$hamperId]['quantity']++;
            } else {
                $cart['hampers'][$hamperId] = [
                    "id" => $hamper->id, // Add hamper ID to cart item for easier identification
                    "nama" => $hamper->nama,
                    "quantity" => 1,
                    "harga" => $hamper->harga,
                    "image" => $hamper->image
                ];
            }

            Session::put('cart', $cart);

            return redirect()->back()->with('success', 'Hamper added to cart successfully!');
        }

        return redirect()->back()->with('error', 'No product or hamper selected.');
    }

    public function showCart()
    {
        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $cart = Session::get('cart', []);
        $promoPoints = PromoPoint::where('tanggal_dimulai', '<=', $startDate)
            ->where('tanggal_berakhir', '>=', $endDate)->where('jumlah_point', '>', 0)
            ->get();
        return view('customer.cart.cart', compact('cart', 'promoPoints'));
    }

    public function updateCart(Request $request)
    {
        $id = $request->input('id');
        $quantity = $request->input('quantity');

        $cart = Session::get('cart', []);

        // Update product quantity if exists in the cart
        if (isset($cart['products'][$id])) {
            if ($quantity > 0) {
                $cart['products'][$id]['quantity'] = $quantity;
            } else {
                unset($cart['products'][$id]);
            }
        }

        // Update hamper quantity if exists in the cart
        if (isset($cart['hampers'][$id])) {
            if ($quantity > 0) {
                $cart['hampers'][$id]['quantity'] = $quantity;
            } else {
                unset($cart['hampers'][$id]);
            }
        }

        Session::put('cart', $cart);

        return redirect()->back()->with('success', 'Cart updated successfully!');
    }

    public function removeFromCart(Request $request)
    {
        $id = $request->input('id');

        $cart = Session::get('cart', []);

        // Remove product if exists in the cart
        if (isset($cart['products'][$id])) {
            unset($cart['products'][$id]);
        }

        // Remove hamper if exists in the cart
        if (isset($cart['hampers'][$id])) {
            unset($cart['hampers'][$id]);
        }

        Session::put('cart', $cart);

        return redirect()->back()->with('success', 'Item removed from cart successfully!');
    }


    public function applyPromo(Request $request)
    {
        $promoId = $request->input('promo_id');
        $totalPrice = $request->input('total_price');

        // Retrieve the promo details using the promo ID
        $promo = PromoPoint::find($promoId);

        if ($promo) {
            $discount = $promo->jumlah_point; // Assuming jumlah_point is the discount value

            // Get the current claimed promo IDs and total discount from session
            $claimedPromoIds = session('claimed_promo_ids', []);
            $totalDiscount = session('total_discount', 0);

            // Add the new promo to the claimed promo IDs and update the total discount
            if (!in_array($promoId, $claimedPromoIds)) {
                $claimedPromoIds[] = $promoId;
                $totalDiscount += $discount;
            }

            $totalPriceAfterDiscount = $totalPrice - $totalDiscount;

            // Store the updated claimed promo IDs and new total discount in session
            session(['claimed_promo_ids' => $claimedPromoIds]);
            session(['total_discount' => $totalDiscount]);
            session(['total_price_after_discount' => $totalPriceAfterDiscount]);

            if($request->input('jenis')=='remove'){
                session(['claimed_promo_ids' => []]);
                session(['total_discount' => 0]);
                session(['total_price_after_discount' => 0]);
                return redirect()->back()->with('success', 'Promo removed successfully!');
            }
        }

        return redirect()->back()->with('success', 'Promo applied successfully!');
    }


    public function applyPoints(Request $request)
    {
        $pointUser = $request->input('point_user');
        $totalPrice = $request->input('total_price');

        // dd($pointUser);

        // Get the current claimed promo IDs and total discount from session
        $claimedPromoIds = session('claimed_promo_ids', []);
        $totalDiscount = session('total_discount', 0);

        $totalPriceAfterDiscount = $totalPrice - $totalDiscount - ($pointUser*100);

        // Store the updated total price after discount in session
        session(['total_price_after_discount' => $totalPriceAfterDiscount]);
        session(['status_claim' => 'true']);
        if($request->input('jenis')=='remove'){
            session(['claimed_promo_ids' => []]);
            session(['total_discount' => 0]);
            session(['total_price_after_discount' => 0]);
            session(['status_claim' => 'false']);
            return redirect()->back()->with('success', 'Points removed successfully!');
        }

        return redirect()->back()->with('success', 'Points applied successfully!');
    }

public function cancelPointClaim(Request $request)
{
    session(['claimed_promo_ids' => []]);
    session(['total_discount' => 0]);
    session(['total_price_after_discount' => 0]);

    return redirect()->back();
}

public function checkout(Request $request)
{
    // Retrieve the cart from session
    $cart = session('cart', []);

    // Retrieve the products array from cart
    $products = $cart['products'] ?? [];

    // Calculate the total price
    $totalPrice = array_reduce($products, function ($carry, $item) {
        if (isset($item['harga']) && isset($item['quantity'])) {
            return $carry + $item['harga'] * $item['quantity'];
        } else {
            throw new \Exception('Invalid item structure in cart.');
        }
    }, 0);

    // Retrieve the total price after discount from session, default to totalPrice if not set
    $totalPriceAfterDiscount = session('total_price_after_discount', $totalPrice);

    // Retrieve applied promos and their discounts
    $appliedPromos = session('total_discount', []);
    $transactionId = rand(1000, 9999);

    // Create a new order
    $order = new Order();
    $order->total_price = $totalPriceAfterDiscount;
    $order->transaksi_id = $transactionId;
    $order->id_transaksi = $transactionId;
    $order->save();

    // Create order items and reduce product stock
    DB::transaction(function () use ($products, $order) {
        foreach ($products as $id => $details) {
            $product = Dukpro::find($id);

            // Ensure there is enough stock
            if ($product->stok >= $details['quantity']) {
                // Create order item
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                $orderItem->dukpro_id = $id;
                $orderItem->quantity = $details['quantity'];
                $orderItem->price = $details['harga'];
                $orderItem->save();

                // Reduce stock
                $product->stok -= $details['quantity'];
                $product->save();
            } else {
                throw new \Exception('Not enough stock for product: ' . $product->nama);
            }
        }
    });

    // Clear the cart and other session data
    session()->forget(['cart', 'claimed_promo_ids', 'total_price_after_discount']);

    return view('receipt', ['order' => $order, 'appliedPromos' => $appliedPromos]);
}

}
