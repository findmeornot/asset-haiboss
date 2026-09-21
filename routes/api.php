<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarangMasukController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('api.logout-all');
        Route::get('/profile', [AuthController::class, 'profile'])->name('api.profile');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->name('api.profile.update');
        Route::put('/profile/password', [AuthController::class, 'changePassword'])->name('api.profile.password');
        Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar'])->name('api.profile.avatar.upload');
        Route::delete('/profile/avatar', [AuthController::class, 'deleteAvatar'])->name('api.profile.avatar.delete');

        Route::get('/assets', [AssetController::class, 'index'])->name('api.assets.index');
        Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('api.assets.show');

        Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');

        Route::get('/campuses', [CampusController::class, 'index'])->name('api.campuses.index');
        Route::get('/locations', [LocationController::class, 'index'])->name('api.locations.index');

        Route::get('/barang-masuk', [BarangMasukController::class, 'index'])->name('api.barang-masuk.index');
        Route::post('/barang-masuk', [BarangMasukController::class, 'store'])->name('api.barang-masuk.store');
        Route::get('/barang-masuk/{barangMasuk}', [BarangMasukController::class, 'show'])->name('api.barang-masuk.show');
        Route::put('/barang-masuk/{barangMasuk}', [BarangMasukController::class, 'update'])->name('api.barang-masuk.update');
        Route::delete('/barang-masuk/{barangMasuk}', [BarangMasukController::class, 'destroy'])->name('api.barang-masuk.destroy');

        // Pengecekan Barang (OB): foto fisik per posisi + penyelesaian pengecekan.
        Route::post('/barang-masuk/{barangMasuk}/photos', [BarangMasukController::class, 'storePhoto'])->name('api.barang-masuk.photos.store');
        Route::delete('/barang-masuk/{barangMasuk}/photos/{photo}', [BarangMasukController::class, 'destroyPhoto'])->name('api.barang-masuk.photos.destroy');
        Route::get('/barang-masuk/{barangMasuk}/barcode-check', [BarangMasukController::class, 'checkBarcode'])->name('api.barang-masuk.barcode-check');
        Route::post('/barang-masuk/{barangMasuk}/pengecekan', [BarangMasukController::class, 'complete'])->name('api.barang-masuk.pengecekan');
    });
});
