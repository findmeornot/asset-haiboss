<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPurchase;
use App\Models\Category;
use App\Services\DepreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class FinancialPrecisionTest extends TestCase
{
    use DatabaseTruncation;

    public function test_depreciation_with_precision_safe_values()
    {
        $classification = \App\Models\Classification::factory()->create();
        $category = Category::factory()->create(['useful_life' => 8]);
        $category->classifications()->attach($classification);
        
        $asset = Asset::factory()->create([
            'category_id' => $category->id,
            'classification_id' => $classification->id,
        ]);
        
        // Simulating purchase in a specific year to test elapsed years
        // If current year is 2026, and purchased in 2024, starts 2025.
        // Elapsed = 2026 - 2025 + 1 = 2 years.
        AssetPurchase::factory()->create([
            'asset_id' => $asset->id,
            'total_price' => '80000000.00',
            'purchase_date' => Carbon::now()->subYears(2)->format('Y-m-d'),
        ]);

        // Reload relationships
        $asset->load(['purchase', 'category']);

        $financials = DepreciationService::calculate($asset);

        // Cost = 80M, Useful Life = 8
        // Annual = 10M
        $this->assertEquals('80000000.00', $financials['acquisition_cost']);
        $this->assertEquals('10000000.00', $financials['annual_depreciation']);
        
        // 2 years elapsed -> 20M accumulated
        $this->assertEquals('20000000.00', $financials['accumulated_depreciation']);
        
        // Book value = 80M - 20M = 60M
        $this->assertEquals('60000000.00', $financials['book_value']);
        $this->assertEquals(6, $financials['remaining_useful_life']);
    }

    public function test_land_no_depreciation()
    {
        $classification = \App\Models\Classification::factory()->create();
        $category = Category::factory()->create(['useful_life' => 0]);
        $category->classifications()->attach($classification);
        
        $asset = Asset::factory()->create([
            'category_id' => $category->id,
            'classification_id' => $classification->id,
        ]);
        
        AssetPurchase::factory()->create([
            'asset_id' => $asset->id,
            'total_price' => '500000000.00',
            'purchase_date' => Carbon::now()->subYears(5)->format('Y-m-d'),
        ]);

        $asset->load(['purchase', 'category']);

        $financials = DepreciationService::calculate($asset);

        $this->assertEquals('500000000.00', $financials['acquisition_cost']);
        $this->assertEquals('0.00', $financials['annual_depreciation']);
        $this->assertEquals('0.00', $financials['accumulated_depreciation']);
        $this->assertEquals('500000000.00', $financials['book_value']);
        $this->assertEquals(0, $financials['remaining_useful_life']);
    }

    public function test_division_precision_no_drift()
    {
        $classification = \App\Models\Classification::factory()->create();
        $category = Category::factory()->create(['useful_life' => 3]);
        $category->classifications()->attach($classification);
        
        $asset = Asset::factory()->create([
            'category_id' => $category->id,
            'classification_id' => $classification->id,
        ]);
        
        AssetPurchase::factory()->create([
            'asset_id' => $asset->id,
            'total_price' => '100.00',
            'purchase_date' => Carbon::now()->subYears(1)->format('Y-m-d'),
        ]);

        $asset->load(['purchase', 'category']);

        $financials = DepreciationService::calculate($asset);

        $this->assertEquals('100.00', $financials['acquisition_cost']);
        // 100 / 3 = 33.33 in BCMath scale 2
        $this->assertEquals('33.33', $financials['annual_depreciation']);
        
        // 1 year elapsed
        $this->assertEquals('33.33', $financials['accumulated_depreciation']);
        
        // 100.00 - 33.33 = 66.67
        $this->assertEquals('66.67', $financials['book_value']);
    }

    public function test_fully_depreciated_asset()
    {
        $classification = \App\Models\Classification::factory()->create();
        $category = Category::factory()->create(['useful_life' => 5]);
        $category->classifications()->attach($classification);
        
        $asset = Asset::factory()->create([
            'category_id' => $category->id,
            'classification_id' => $classification->id,
        ]);
        
        // Bought 10 years ago
        AssetPurchase::factory()->create([
            'asset_id' => $asset->id,
            'total_price' => '100000.00',
            'purchase_date' => Carbon::now()->subYears(10)->format('Y-m-d'),
        ]);

        $asset->load(['purchase', 'category']);

        $financials = DepreciationService::calculate($asset);

        // Should not depreciate below 0 or have > 100000 accumulated
        $this->assertEquals('100000.00', $financials['accumulated_depreciation']);
        $this->assertEquals('0.00', $financials['book_value']);
        $this->assertEquals(0, $financials['remaining_useful_life']);
    }
}
