<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    // Method untuk ambil semua layanan (versi lengkap dengan URL gambar)
    public function getServices()
    {
        $services = Service::all()->map(function ($service) {
            $service->image_url = $service->image 
                ? url('storage/gambar/' . $service->image)
                : null;
            return $service;
        });

        return response()->json($services);
    }

    // Get semua layanan (bisa filter by category_id)
    public function index(Request $request)
    {
        $categoryId = $request->query('category_id');

        $services = Service::query()
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => $service->price,
                    'description' => $service->description,
                    'category_id' => $service->category_id,
                    'image' => $service->image 
                        ? url('storage/gambar/' . $service->image) 
                        : null,
                    'duration' => $service->duration, // ✅ Tambahkan ke response
                ];
            });

        return response()->json($services);
    }

    // Tambah layanan baru dengan gambar
    public function store(Request $request)
    {
        if ($request->user()->role !== 'pemilik') {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'duration' => 'required|integer|min:1', // Validasi durasi
        ]);

        // Upload gambar jika ada
        $imageName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('storage/gambar'), $imageName);
        }

        $service = Service::create([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'price' => $request->price,
            'description' => $request->description,
            'image' => $imageName,
            'duration' => $request->duration, // Simpan durasi
        ]);

        return response()->json([
            'message' => 'Layanan berhasil ditambahkan',
            'data' => [
                'id' => $service->id,
                'name' => $service->name,
                'price' => $service->price,
                'description' => $service->description,
                'category_id' => $service->category_id,
                'image' => $service->image 
                    ? url('storage/gambar/' . $service->image) 
                    : null,
                'duration' => $service->duration, // Tambahkan ke response
            ]
        ], 201);
    }

    // Hapus layanan + hapus gambar
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'pemilik') {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $service = Service::findOrFail($id);

        // Hapus gambar dari storage jika ada
        if ($service->image) {
            $imagePath = public_path('storage/gambar/' . $service->image);
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        $service->delete();

        return response()->json(['message' => 'Layanan berhasil dihapus']);
    }
}
