<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'gambar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $path = $request->file('gambar')->store('public/gambar');
        $url = asset(Storage::url($path));

        return response()->json([
            'message' => 'Upload berhasil',
            'url' => $url
        ]);
    }
}
