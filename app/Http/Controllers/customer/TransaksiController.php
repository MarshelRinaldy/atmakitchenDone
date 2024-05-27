<?php

namespace App\Http\Controllers\customer;

use App\Http\Controllers\Controller;
use App\Models\DetailTransaksi;
use App\Models\Dukpro;
use App\Models\Hampers;
use App\Models\HampersDetail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PromoPoint;
use App\Models\Transaksi;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransaksiController extends Controller
{
    public function create()
    {
        return view('customer.transaksi.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'metode_pembayaran' => 'required|string',
            'tanggal_transaksi' => 'required|date',
            'status_pengantaran' => 'required|string',
            'jenis_delivery' => 'required_if:status_pengantaran,delivery|string|nullable',
            'alamat_pengantaran' => 'required_if:status_pengantaran,delivery|string|nullable',
        ]);
        // Retrieve the total price after discount from session, default to totalPrice if not set
        // Retrieve the cart from session
        $cart = session('cart', []);
        // Check if products key exists in cart
        if (!isset($cart['products'])) {
            throw new \Exception('Cart does not contain products.');
        }
        // gabungkan $cart['products'] dengan $cart['hampers'] tapi jika tidak kosong, jika salah satu kosong, maka gunakan yang tidak kosong
        $products = $cart['products'];

        // Validate cart items
        foreach ($products as $item) {
            if (!isset($item['harga']) || !isset($item['quantity'])) {
                throw new \Exception('Cart item is missing price or quantity.');
            }
        }

        // Calculate the total price
        $totalPrice = array_reduce(
            $products,
            function ($carry, $item) {
                return $carry + $item['harga'] * $item['quantity'];
            },
            0
        );
        //tambahkan total price dengan hampers jika ada
        if (isset($cart['hampers'])) {
            $hampers = $cart['hampers'];
            foreach ($hampers as $item) {
                if (!isset($item['harga']) || !isset($item['quantity'])) {
                    throw new \Exception('Cart item is missing price or quantity.');
                }
            }
            $totalPrice += array_reduce(
                $hampers,
                function ($carry, $item) {
                    return $carry + $item['harga'] * $item['quantity'];
                },
                0
            );
        }
        // Retrieve applied promos and their discounts
        $appliedPromos = session('total_discount', []);
        if($appliedPromos){
            $totalPriceAfterDiscount = session('total_price_after_discount', $totalPrice);
        }else{
            $totalPriceAfterDiscount = $totalPrice;
        }
        $totalPoint =  $totalPrice-$totalPriceAfterDiscount;
        $transaksi = new Transaksi();
        $transaksi->user_id = auth()->user()->id; // Assuming the user is authenticated
        $transaksi->metode_pembayaran = $request->metode_pembayaran;
        $transaksi->tanggal_transaksi = Carbon::parse($request->tanggal_transaksi);
        $transaksi->status_pengantaran = $request->status_pengantaran;
        $transaksi->jenis_delivery = $request->jenis_delivery;
        $transaksi->alamat_pengantaran = $request->alamat_pengantaran;
        $transaksi->jumlah_transaksi = $totalPriceAfterDiscount;
        $transaksi->bukti_pembayaran = 'belum diunggah';
        if($request->status_pengantaran == 'delivery'){
            $transaksi->status_transaksi = 'menunggu konfirmasi ongkir';
        }else{
            $transaksi->status_transaksi = 'menunggu pembayaran';
        }
        // no_transaksi
        $transaksi->no_transaksi = strtoupper(Str::random(6)) . rand(1000, 9999);
        $transaksi->total_harga = $totalPriceAfterDiscount;

        $transaksi->save();

        $data['transaksi'] = $transaksi;



        $transactionId = strtoupper(Str::random(6)) . rand(1000, 9999);

        if($totalPoint>0){
            $totalPointPakai = 0- ($totalPoint/100);
            $promo = new PromoPoint();
            $promo->nama = $transaksi->user->name;
            $promo->jumlah_point = $totalPointPakai;
            $promo->tanggal_dimulai = now();
            $promo->tanggal_berakhir = now()->addMonth();
            $promo->deskripsi = 'Pemakaian poin dari transaksi ' . $transaksi->id;
            $promo->save();
        }


        // Create a new order
        $order = new Order();
        $order->total_price = $totalPriceAfterDiscount;
        $order->id_transaksi = $transaksi->id;
        $order->save();

        // Create order items and reduce product stock
        DB::transaction(function () use ($products, $order, $transaksi) {
            foreach ($products as $id => $details) {
                $product = Dukpro::find($id);
                // Ensure there is enough stock
                if ($product->stok >= $details['quantity']) {
                    $orderItem = new DetailTransaksi();
                    $orderItem->transaksi_id = $transaksi->id;
                    $orderItem->produk_id = $id;
                    $orderItem->jumlah_produk = $details['quantity'];
                    $orderItem->save();
                    // Reduce stock
                    $product->stok -= $details['quantity'];
                    $product->save();
                } else {
                    throw new \Exception('Not enough stock for product: ' . $product->nama);
                }
            }
        });
        if (isset($cart['hampers'])) {
            $hampers = $cart['hampers'];
            DB::transaction(function () use ($hampers, $order, $transaksi) {
                foreach ($hampers as $id => $details) {
                    $hamper = Hampers::find($id);
                    // Ensure there is enough stock
                    if ($hamper->stok >= $details['quantity']) {
                        $orderItem = new DetailTransaksi();
                        $orderItem->transaksi_id = $transaksi->id;
                        $orderItem->produk_id = $id;
                        $orderItem->is_hampers = true;
                        $orderItem->jumlah_produk = $details['quantity'];
                        $orderItem->save();
                        // Reduce stock
                        $hamper->stok -= $details['quantity'];
                        $hamper->save();
                    } else {
                        throw new \Exception('Not enough stock for product: ' . $hamper->nama);
                    }
                }
            });
        }

        // return view('customer.transaksi.success', $data);
        return redirect()->route('customer.transaksi.nota', $transaksi->id);
    }


    public function upload_bukti(Request $request)
    {
        $request->validate([
            'buktiTransfer' => 'required|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->file('buktiTransfer')) {
            $fileName = time() . '.' . $request->buktiTransfer->extension();
            $request->buktiTransfer->move(public_path('uploads'), $fileName);

            $transaksi = Transaksi::find($request->id); // assuming you have transaction ID in request
            $transaksi->image_bukti_pembayaran = $fileName;
            $transaksi->status_transaksi = 'menunggu konfirmasi';
            $transaksi->save();

            // return back()->with('success', 'Bukti transfer berhasil diunggah.');

            return view('customer.transaksi.riwayat');
        }

        return 'gagal';
    }
    public function showNota($id)
{
    $transaksi = Transaksi::with('user', 'detailTransaksis.produk')->findOrFail($id);
    $data = [
        'transaksi' => $transaksi,
        'order' => $transaksi->order,
        'user' => $transaksi->user,
        'orderItems' => $transaksi->detailTransaksis
    ];

    // dd($data);
    $pembelian = $transaksi->total_harga;
        $poin = 0;
        if ($pembelian >= 1000000) {
            $poin += intdiv($pembelian, 1000000) * 200;
            $pembelian %= 1000000;
        }
        if ($pembelian >= 500000) {
            $poin += intdiv($pembelian, 500000) * 75;
            $pembelian %= 500000;
        }
        if ($pembelian >= 100000) {
            $poin += intdiv($pembelian, 100000) * 15;
            $pembelian %= 100000;
        }
        if ($pembelian >= 10000) {
            $poin += intdiv($pembelian, 10000);
        }



        $tanggal_lahir = $transaksi->user->date_of_birth;
        $tanggal_transaksi = $transaksi->tanggal_transaksi;
        // Menghitung periode H-3 sampai H+3 dari tanggal lahir
        $dob = new DateTime($tanggal_lahir);
        $tanggal_transaksi = new DateTime($tanggal_transaksi);

        $start = (clone $dob)->modify('-3 days');
        $end = (clone $dob)->modify('+3 days');

        // Sesuaikan tahun transaksi dengan tahun tanggal lahir
        $start->setDate($tanggal_transaksi->format('Y'), $start->format('m'), $start->format('d'));
        $end->setDate($tanggal_transaksi->format('Y'), $end->format('m'), $end->format('d'));

        // Jika transaksi dalam periode H-3 sampai H+3
        if ($tanggal_transaksi >= $start && $tanggal_transaksi <= $end) {
            $poin *= 2;
        }


        $total_point = 0;
        //loop promopoint
        $promoPoints = PromoPoint::where('tanggal_dimulai', '<=', $tanggal_transaksi)
            ->where('tanggal_berakhir', '>=', $tanggal_transaksi)->sum('jumlah_point');
        // foreach ($promoPoints as $promoPoint) {
        //     $total_point += $promoPoint->jumlah_point;
        // }
        $total_point = $promoPoints->jumlah_point ?? 0;

        if(session('total_price_after_discount') > 0){
            $total_point = $total_point - ($transaksi->total_harga - session('total_price_after_discount')) + $poin;
        }else{
            $total_point += $poin;
        }
        $data['total_point'] = $total_point;
        $data['poin'] = $poin;

    return view('customer.transaksi.nota', $data);
}
}
