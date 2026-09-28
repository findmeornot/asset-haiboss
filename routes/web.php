<?php

use App\Http\Controllers\AssetImportTemplateController;
use App\Http\Controllers\ReportController;
use App\Models\AssetMovement;
use App\Services\BeritaAcaraService;
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
