<?php

use Illuminate\Support\Facades\Route;



Route::get('/asset/{id}/print-label', function ($id) {
    abort_unless(auth()->check(), 403);
    $asset = \App\Models\Asset::findOrFail($id);
    return view('asset-label-print', compact('asset'));
})->name('asset.label.print')->middleware('auth');

Route::get('/movement/{id}/berita-acara', function ($id, \App\Services\BeritaAcaraService $baService) {
    abort_unless(auth()->check(), 403);
    $movement = \App\Models\AssetMovement::findOrFail($id);
    abort_unless($movement->status === 'completed', 404, 'Mutasi belum selesai.');
    return $baService->generateForMovement($movement);
})->name('asset.movement.ba')->middleware('auth');

Route::get('/report/export', [\App\Http\Controllers\ReportController::class, 'exportExcel'])
    ->name('report.export.excel')
    ->middleware('auth');
