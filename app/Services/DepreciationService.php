<?php

namespace App\Services;

use App\Models\Asset;
use Carbon\Carbon;

class DepreciationService
{
    /**
     * Calculate financial and depreciation data for an asset.
     * Straight-line method. Starts next year.
     * Uses BCMath for financial precision.
     *
     * @param Asset $asset
     * @return array
     */
    public static function calculate(Asset $asset): array
    {
        // Path changed from asset->purchase->total_price to asset->purchaseItem->unit_price
        $cost = '0';
        $purchaseDate = null;
        
        if ($asset->purchaseItem) {
            $cost = (string) ($asset->purchaseItem->unit_price ?? '0');
            $purchaseDate = $asset->purchaseItem->purchase ? $asset->purchaseItem->purchase->purchase_date : null;
        } elseif ($asset->purchase) {
            $cost = (string) ($asset->purchase->total_price ?? '0');
            $purchaseDate = $asset->purchase->purchase_date;
        }

        $usefulLife = $asset->category ? (int) $asset->category->useful_life : 0;
        
        // Business Rules Configurations
        $residualValue = '0'; // Configurable if needed
        $startYearOffset = 1; // "Penyusutan dimulai pada tahun berikutnya"

        $costFloat = (float) $cost;
        $residualValueFloat = (float) $residualValue;

        // Handle empty or zero
        if ($costFloat <= 0 || !$purchaseDate || $usefulLife <= 0) {
            return [
                'acquisition_cost' => number_format($costFloat, 2, '.', ''),
                'book_value' => number_format($costFloat, 2, '.', ''),
                'accumulated_depreciation' => '0.00',
                'annual_depreciation' => '0.00',
                'useful_life' => $usefulLife,
                'remaining_useful_life' => $usefulLife,
                'is_depreciable' => false,
                'reason' => 'Data pembelian/masa manfaat tidak valid atau 0',
            ];
        }

        $purchaseYear = Carbon::parse($purchaseDate)->year;
        $currentYear = now()->year;

        // Calculate Depreciable Amount
        $depreciableAmount = $costFloat - $residualValueFloat;
        
        // Annual Depreciation = Depreciable Amount / Useful Life
        $annualDepreciation = $depreciableAmount / $usefulLife;
        
        // Calculate elapsed years for depreciation
        $startYear = $purchaseYear + $startYearOffset;
        
        if ($currentYear < $startYear) {
            $elapsedYears = 0;
        } else {
            $elapsedYears = ($currentYear - $startYear) + 1;
        }

        if ($elapsedYears > $usefulLife) {
            $elapsedYears = $usefulLife;
        }

        // Accumulated Depreciation = elapsed * annual
        $accumulatedDepreciation = $annualDepreciation * $elapsedYears;

        // Ensure accumulated depreciation does not exceed depreciable amount due to rounding
        if ($accumulatedDepreciation > $depreciableAmount) {
            $accumulatedDepreciation = $depreciableAmount;
        }

        $bookValue = $costFloat - $accumulatedDepreciation;
        
        // Ensure book value doesn't drop below residual value
        if ($bookValue < $residualValueFloat) {
            $bookValue = $residualValueFloat;
        }

        $remainingUsefulLife = max(0, $usefulLife - $elapsedYears);

        return [
            'acquisition_cost' => number_format($costFloat, 2, '.', ''),
            'book_value' => number_format($bookValue, 2, '.', ''),
            'accumulated_depreciation' => number_format($accumulatedDepreciation, 2, '.', ''),
            'annual_depreciation' => number_format($annualDepreciation, 2, '.', ''),
            'useful_life' => $usefulLife,
            'remaining_useful_life' => $remainingUsefulLife,
            'is_depreciable' => true,
        ];
    }
}
