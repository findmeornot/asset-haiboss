<?php

use App\Http\Controllers\AssetImportTemplateController;
use App\Http\Controllers\ReportController;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Services\BeritaAcaraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->hasRole('Superadmin') || $user->hasPermissionTo('panel.admin')) {
            return redirect('admin');
        } elseif ($user->hasPermissionTo('panel.inventory')) {
            return redirect('inventory');
        }
    }

    return redirect('admin');
});

Route::get('/movement/{movement}/berita-acara', function (AssetMovement $movement, BeritaAcaraService $baService) {
    abort_unless(Auth::check(), 403);
    abort_unless($movement->status === 'completed', 404, 'Mutasi belum selesai.');

    return $baService->generateForMovement($movement);
})->name('asset.movement.ba')->middleware('auth');

Route::get('/report/export', [ReportController::class, 'exportExcel'])
    ->name('report.export.excel')
    ->middleware('auth');

Route::get('/asset/import/template', [AssetImportTemplateController::class, 'downloadCsv'])
    ->name('asset.import.template')
    ->middleware('auth');

Route::get('/checklist/print', function (Request $request) {
    $query = Asset::query()->with(['category', 'classification', 'campus', 'location', 'pic']);

    if ($request->filled('location_id')) {
        $query->where('location_id', $request->integer('location_id'));
    } elseif ($request->filled('ids')) {
        $query->whereIn('id', array_filter(explode(',', $request->string('ids'))));
    } else {
        abort(404);
    }

    $assets = $query->orderBy('name')->orderBy('inventory_number')->get();

    abort_if($assets->isEmpty(), 404, 'Tidak ada barang untuk dicetak.');

    return view('checklist-print', ['assets' => $assets]);
})->name('checklist.print')->middleware('auth');

Route::get('/asset-photo-thumb/{path}', function (string $path) {
    $disk = \Illuminate\Support\Facades\Storage::disk(config('filesystems.default', 's3'));
    if (!$disk->exists($path)) {
        abort(404);
    }
    
    $cacheKey = 'thumb_' . md5($path);
    $imgData = \Illuminate\Support\Facades\Cache::rememberForever($cacheKey, function () use ($disk, $path) {
        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read($disk->get($path));
            return $image->scale(down: true, width: 400)->toJpeg(75)->toString();
        } catch (\Exception $e) {
            return $disk->get($path);
        }
    });

    return response($imgData, 200)
        ->header('Content-Type', 'image/jpeg')
        ->header('Cache-Control', 'public, max-age=31536000');
})->where('path', '.*')->name('asset.photo.thumb');
