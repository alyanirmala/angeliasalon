<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

// Test route - public
Route::get('/test', function () {
    return response()->json(['status' => 'API is working']);
});

// Auth routes - public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Verifikasi email (link dari email)
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return response()->json(['message' => 'Email berhasil diverifikasi']);
})->middleware(['auth:sanctum', 'signed'])->name('verification.verify');

// Upload gambar - public (kalau mau bisa dipindah ke middleware auth)
Route::post('/upload-gambar', [UploadController::class, 'upload']);

// Group route dengan middleware auth
Route::middleware('auth:sanctum')->group(function () {

    // Kirim ulang link verifikasi email
    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email sudah terverifikasi']);
        }

        $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Link verifikasi dikirim ulang']);
    })->middleware('throttle:6,1');

    // Auth
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Karyawan (CRUD dasar)
    Route::apiResource('karyawan', KaryawanController::class)->only(['index', 'store', 'destroy']);
    Route::put('/karyawan/{id}/approve', [KaryawanController::class, 'approve']);


    // Category
    Route::apiResource('categories', CategoryController::class)->only(['index', 'store']);

    // Service (CRUD dasar)
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

    // Booking
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/accepted', [BookingController::class, 'acceptedBookings']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::put('/bookings', [BookingController::class, 'update']);

    // Pelanggan
    Route::get('/pelanggan', [PelangganController::class, 'index']);

    // Cart
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);
    Route::post('/cart/checkout', [CartController::class, 'checkout']);
});
