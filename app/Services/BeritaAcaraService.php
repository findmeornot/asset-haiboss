<?php

namespace App\Services;

use App\Models\AssetMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;

class BeritaAcaraService
{
    /**
     * Generate the Berita Acara Document for a completed movement.
     * This architecture allows easy swapping of templates in the future
     * or even switching to a PDF library like DomPDF.
     *
     * @param AssetMovement $movement
     * @param string $template
     * @return View
     */
    public function generateForMovement(AssetMovement $movement, string $template = 'documents.berita-acara-mutasi'): View
    {
        // In the future, you can fetch the template name from DB settings
        
        return ViewFacade::make($template, [
            'movement' => $movement,
            'asset' => $movement->asset,
            'sourceCampus' => $movement->sourceCampus,
            'sourceLocation' => $movement->sourceLocation,
            'sourcePic' => $movement->sourcePic,
            'destinationCampus' => $movement->destinationCampus,
            'destinationLocation' => $movement->destinationLocation,
            'destinationPic' => $movement->destinationPic,
            'approvedBy' => $movement->approvedBy,
            'requestedBy' => $movement->requestedBy,
            'date' => $movement->movement_date ?? now(),
        ]);
    }
}
