<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Services\BeritaAcaraService;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AssetImportTemplateController;

Route::get('/', function () {
    return redirect('admin');
});

Route::get('/asset/{asset}/print-label', function (Asset $asset) {
    abort_unless(Auth::check(), 403);
    return view('asset-label-print', compact('asset'));
})->name('asset.label.print')->middleware('auth');

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
