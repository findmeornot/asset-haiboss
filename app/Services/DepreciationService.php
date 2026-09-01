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
        $cost = $asset->purchase ? (string) ($asset->purchase->total_price ?? '0') : '0';
        $purchaseDate = $asset->purchase ? $asset->purchase->purchase_date : null;
        $usefulLife = $asset->category ? (int) $asset->category->useful_life : 0;
        
        // Business Rules Configurations
        $residualValue = '0'; // Configurable if needed
        $startYearOffset = 1; // "Penyusutan dimulai pada tahun berikutnya"

        // Handle empty or zero
        if (bccomp($cost, '0', 2) <= 0 || !$purchaseDate || $usefulLife <= 0) {
            return [
                'acquisition_cost' => $cost,
                'book_value' => $cost,
                'accumulated_depreciation' => '0',
                'annual_depreciation' => '0',
                'useful_life' => $usefulLife,
                'remaining_useful_life' => $usefulLife,
                'is_depreciable' => false,
                'reason' => 'Data pembelian/masa manfaat tidak valid atau 0',
            ];
        }

        $purchaseYear = Carbon::parse($purchaseDate)->year;
        $currentYear = now()->year;

        // Calculate Depreciable Amount
        $depreciableAmount = bcsub($cost, $residualValue, 2);
        
        // Annual Depreciation = Depreciable Amount / Useful Life
        $annualDepreciation = bcdiv($depreciableAmount, (string) $usefulLife, 2);
        
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
        $accumulatedDepreciation = bcmul($annualDepreciation, (string) $elapsedYears, 2);

        // Ensure accumulated depreciation does not exceed depreciable amount due to rounding
        if (bccomp($accumulatedDepreciation, $depreciableAmount, 2) > 0) {
            $accumulatedDepreciation = $depreciableAmount;
        }

        $bookValue = bcsub($cost, $accumulatedDepreciation, 2);
        
        // Ensure book value doesn't drop below residual value
        if (bccomp($bookValue, $residualValue, 2) < 0) {
            $bookValue = $residualValue;
        }

        $remainingUsefulLife = max(0, $usefulLife - $elapsedYears);

        return [
            'acquisition_cost' => $cost,
            'book_value' => $bookValue,
            'accumulated_depreciation' => $accumulatedDepreciation,
            'annual_depreciation' => $annualDepreciation,
            'useful_life' => $usefulLife,
            'remaining_useful_life' => $remainingUsefulLife,
            'is_depreciable' => true,
        ];
    }
}
