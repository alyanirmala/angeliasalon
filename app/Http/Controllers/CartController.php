<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Cart;
use App\Models\Booking;
use Carbon\Carbon;

class CartController extends Controller
{
    /**
     * Menampilkan semua item di keranjang user yang sedang login.
     */
    public function index(Request $request)
    {
        $carts = Cart::with('service')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json($carts);
    }

    /**
     * Menambahkan layanan ke dalam keranjang.
     */
    public function store(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::create([
            'user_id'    => $request->user()->id,
            'service_id' => $request->service_id,
            'quantity'   => $request->quantity,
        ]);

        return response()->json([
            'message' => 'Item added to cart',
            'data'    => $cart,
        ], 201);
    }

    /**
     * Mengubah jumlah layanan di keranjang.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $cart->update([
            'quantity' => $request->quantity,
        ]);

        return response()->json([
            'message' => 'Quantity updated',
            'data'    => $cart,
        ]);
    }

    /**
     * Menghapus item dari keranjang.
     */
    public function destroy(Request $request, $id)
    {
        $cart = Cart::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $cart->delete();

        return response()->json([
            'message' => 'Item removed from cart',
        ]);
    }

    /**
     * Checkout keranjang dan buat booking.
     */
    public function checkout(Request $request)
{
    $user = $request->user();

    $request->validate([
        'tanggal_booking' => 'required|date',
        'jam'             => 'required|date_format:H:i',
    ]);

    $tanggal = $request->tanggal_booking;
    $jamMulai = \Carbon\Carbon::parse($request->jam); // ← fleksibel, bisa H:i atau H:i:s

    $carts = \App\Models\Cart::with('service')
        ->where('user_id', $user->id)
        ->get();

    if ($carts->isEmpty()) {
        return response()->json(['message' => 'Keranjang kosong'], 400);
    }

    \DB::beginTransaction();

    try {
        foreach ($carts as $cart) {
            $service = $cart->service;
            $durasi = $service->duration;
            $jamSelesai = $jamMulai->copy()->addMinutes($durasi);

            // 1. Cek slot penuh (3 booking diterima untuk layanan ini di jam sama)
            $jumlahBookingJamSama = \App\Models\Booking::where('tanggal_booking', $tanggal)
                ->where('jam', $request->jam)
                ->where('service_id', $service->id)
                ->where('status', 'diterima')
                ->count();

            if ($jumlahBookingJamSama >= 3) {
                \DB::rollBack();
                return response()->json([
                    'message' => "Slot penuh untuk layanan '{$service->name}' pada jam {$request->jam}."
                ], 409);
            }

            // 2. Cek bentrok waktu
            $semuaBooking = \App\Models\Booking::with('service:id,duration')
                ->where('tanggal_booking', $tanggal)
                ->where('service_id', $service->id)
                ->where('status', 'diterima')
                ->get();

            foreach ($semuaBooking as $booking) {
                $bookingMulai = \Carbon\Carbon::parse($booking->jam); // ← fix here
                $bookingSelesai = $bookingMulai->copy()->addMinutes($booking->service->duration);

                if (
                    $jamMulai->between($bookingMulai, $bookingSelesai->subMinute()) ||
                    $jamSelesai->between($bookingMulai->addMinute(), $bookingSelesai) ||
                    ($jamMulai->lte($bookingMulai) && $jamSelesai->gte($bookingSelesai))
                ) {
                    \DB::rollBack();
                    return response()->json([
                        'message' => "Layanan '{$service->name}' bentrok dengan jadwal lain di jam {$booking->jam}."
                    ], 409);
                }
            }

            // 3. Simpan booking
            \App\Models\Booking::create([
                'user_id'         => $user->id,
                'service_id'      => $cart->service_id,
                'tanggal_booking' => $tanggal,
                'jam'             => $request->jam,
                'status'          => 'menunggu',
            ]);
        }

        // 4. Bersihkan keranjang
        \App\Models\Cart::where('user_id', $user->id)->delete();

        \DB::commit();

        return response()->json(['message' => 'Semua item berhasil dibooking'], 201);

    } catch (\Exception $e) {
        \DB::rollBack();
        return response()->json([
            'message' => 'Terjadi kesalahan saat booking',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

}
