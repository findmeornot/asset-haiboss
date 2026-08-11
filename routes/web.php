<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Services\BeritaAcaraService;
use App\Http\Controllers\ReportController;

Route::get('/asset/{id}/print-label', function ($id) {
    abort_unless(Auth::check(), 403);
    $asset = Asset::findOrFail($id);
    return view('asset-label-print', compact('asset'));
})->name('asset.label.print')->middleware('auth');

Route::get('/movement/{id}/berita-acara', function ($id, BeritaAcaraService $baService) {
    abort_unless(Auth::check(), 403);
    $movement = AssetMovement::findOrFail($id);
    abort_unless($movement->status === 'completed', 404, 'Mutasi belum selesai.');
    return $baService->generateForMovement($movement);
})->name('asset.movement.ba')->middleware('auth');

Route::get('/report/export', [ReportController::class, 'exportExcel'])
    ->name('report.export.excel')
    ->middleware('auth');

Route::get('/debug-session', function (Request $request) {
    return response()->json([
        'scheme' => $request->getScheme(),
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
        'url' => $request->fullUrl(),
        'session_id' => $request->session()->getId(),
        'csrf_token' => $request->session()->token(),
        'session_cookie' => config('session.cookie'),
    ]);
});