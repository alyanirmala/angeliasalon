<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class PelangganController extends Controller
{
    // Mengambil daftar pelanggan beserta booking dan service-nya
    public function index(Request $request)
    {
        // Misal hanya role pelanggan
        $pelanggan = User::with(['bookings.service'])
            ->where('role', 'pelanggan')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'bookings' => $user->bookings->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'service_name' => $booking->service->name ?? 'Layanan tidak diketahui',
                            'tanggal_booking' => $booking->tanggal_booking,
                            'status' => $booking->status,
                        ];
                    }),
                ];
            });

        return response()->json($pelanggan);
    }
}
