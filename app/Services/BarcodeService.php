<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    /**
     * Generate an SVG barcode for the given value (usually the 6-digit permanent Barcode Number).
     *
     * @param string $value
     * @return string
     */
    public function generateSvg(string $value): string
    {
        // Fallback or empty handle
        if (empty($value)) {
            return '';
        }

        try {
            $generator = new BarcodeGeneratorSVG();
            // Code 128 is excellent for alphanumeric strings like INV-000001
            // 2 is width factor, 50 is height
            return $generator->getBarcode($value, $generator::TYPE_CODE_128, 2, 50, 'black');
        } catch (\Exception $e) {
            // Handle error gracefully
            return '<svg width="200" height="50" xmlns="http://www.w3.org/2000/svg"><text x="10" y="30" fill="red">Barcode Error</text></svg>';
        }
    }
}
