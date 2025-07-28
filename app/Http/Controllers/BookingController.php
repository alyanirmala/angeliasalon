<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function index(Request $request)
    {
    $user = $request->user();

    $query = Booking::with(['user', 'service:id,name,price']); // ← tambahkan price

    if ($user->role == 'pelanggan') {
        $query->where('user_id', $user->id);
    } elseif ($user->role == 'karyawan') {
        $query->where('status', 'menunggu');
    }

    return response()->json($query->get());
    }


    public function store(Request $request)
{
    $request->validate([
        'service_id' => 'required|exists:services,id',
        'tanggal_booking' => 'required|date',
        'jam' => 'required|date_format:H:i',
    ]);

    $user = $request->user();
    $tanggal = $request->tanggal_booking;
    $jamMulai = Carbon::createFromFormat('H:i', $request->jam);

    // Ambil durasi dari layanan
    $service = \App\Models\Service::findOrFail($request->service_id);
    $durasi = $service->duration; // dalam menit
    $jamSelesai = $jamMulai->copy()->addMinutes($durasi);

    // Cek apakah sudah ada 3 booking diterima pada jam yang sama
    $jumlahBookingJamSama = Booking::where('tanggal_booking', $tanggal)
    ->where('jam', $request->jam)
    ->where('service_id', $request->service_id)
    ->where('status', 'diterima')
    ->count();

    if ($jumlahBookingJamSama >= 3) {
        return response()->json([
            'message' => 'Slot sudah penuh. Silakan pilih jam lain.'
        ], 409);
    }

    // Cek bentrok dengan slot waktu berdasarkan durasi
    $semuaBookingHariItu = Booking::with('service:id,duration')
        ->where('tanggal_booking', $tanggal)
        ->where('status', 'diterima')
        ->get();

    foreach ($semuaBookingHariItu as $booking) {
        $bookingJamMulai = Carbon::createFromFormat('H:i:s', $booking->jam);
        $bookingJamSelesai = $bookingJamMulai->copy()->addMinutes($booking->service->duration);

        // Jika booking baru bentrok dengan salah satu yang ada
        if (
            $jamMulai->between($bookingJamMulai, $bookingJamSelesai->subMinute()) ||
            $jamSelesai->between($bookingJamMulai->addMinute(), $bookingJamSelesai) ||
            ($jamMulai->lte($bookingJamMulai) && $jamSelesai->gte($bookingJamSelesai))
        ) {
            return response()->json([
                'message' => 'Slot bertabrakan dengan booking lain. Silakan pilih jam berbeda.'
            ], 409);
        }
    }

    // Jika aman, simpan booking
    $booking = Booking::create([
        'user_id' => $user->id,
        'service_id' => $request->service_id,
        'tanggal_booking' => $tanggal,
        'jam' => $request->jam,
        'status' => 'menunggu',
    ]);

    $booking->load('service:id,name,price');

    return response()->json([
        'message' => 'Booking berhasil dibuat',
        'booking' => $booking
    ], 201);
}
    public function acceptedBookings(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['karyawan', 'pemilik'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $bookings = Booking::with(['user', 'service:id,name,price'])
            ->whereIn('status', ['menunggu', 'diterima'])
            ->get();

        return response()->json($bookings);
    }

    public function update(Request $request)
    {
        if ($request->user()->role !== 'karyawan') {
            return response()->json([
                'message' => 'Unauthorized. Hanya karyawan yang bisa mengubah status booking'
            ], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'tanggal_booking' => 'required|date',
            'status' => 'required|in:menunggu,diterima,ditolak',
        ]);

        $bookings = Booking::with(['user', 'service:id,name'])
            ->where('user_id', $request->user_id)
            ->where('tanggal_booking', $request->tanggal_booking)
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada booking ditemukan untuk user dan tanggal tersebut'
            ], 404);
        }

        foreach ($bookings as $booking) {
            $booking->status = $request->status;
            $booking->save();
        }

        return response()->json([
            'message' => 'Status semua booking berhasil diperbarui',
            'jumlah_diperbarui' => $bookings->count(),
            'data' => $bookings
        ]);
    }
}
