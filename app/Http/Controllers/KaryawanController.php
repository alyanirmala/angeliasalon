<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class KaryawanController extends Controller
{
    // Daftar Karyawan
    public function index(Request $request)
    {
        // Hanya pemilik boleh akses
        if ($request->user()->role !== 'pemilik') {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $karyawan = User::where('role', 'karyawan')->get();
        return response()->json($karyawan);
    }

    // Tambah Karyawan
    public function store(Request $request)
    {
        if ($request->user()->role !== 'pemilik') {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $karyawan = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'karyawan',
        ]);

        return response()->json(['message' => 'Karyawan berhasil ditambahkan', 'karyawan' => $karyawan], 201);
    }

    //status_approve
    public function approve(Request $request, $id)
    {
    if ($request->user()->role !== 'pemilik') {
        return response()->json(['message' => 'Tidak diizinkan'], 403);
    }

    $karyawan = User::where('role', 'karyawan')->findOrFail($id);
    $karyawan->is_approved = true;
    $karyawan->save();

    return response()->json(['message' => 'Karyawan berhasil disetujui']);
    }


    // Hapus Karyawan
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'pemilik') {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $karyawan = User::where('role', 'karyawan')->findOrFail($id);
        $karyawan->delete();

        return response()->json(['message' => 'Karyawan berhasil dihapus']);
    }
}
