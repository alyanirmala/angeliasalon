<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // Ambil semua kategori
    public function index()
    {
        return response()->json(Category::all());
    }

    // Tambah kategori (khusus pemilik/admin)
    public function store(Request $request)
    {
        // Optional: Batasi hanya role 'pemilik'
        if ($request->user()->role !== 'pemilik') {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $category = Category::create([
            'name' => $request->name
        ]);

        return response()->json(['message' => 'Kategori berhasil ditambahkan', 'data' => $category], 201);
    }
}
