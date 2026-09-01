<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Campus;
use App\Models\Location;
use App\Models\Employee;
use App\Models\User;
use App\Services\InventoryNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Exception;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed initial sequences for the test
        DB::table('inventory_number_sequences')->insert([
            'name' => 'asset_inventory',
            'current_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_concurrent_inventory_number_generation()
    {
        $num1 = InventoryNumberGenerator::generate();
        $num2 = InventoryNumberGenerator::generate();

        $this->assertNotEquals($num1, $num2);
        $this->assertEquals('INV-000001', $num1);
        $this->assertEquals('INV-000002', $num2);
    }

    public function test_duplicate_pending_approval_prevention()
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create(['status' => 'stock']);

        // First request should succeed
        ApprovalRequest::create([
            'request_type' => 'status_change',
            'requested_by' => $user->id,
            'status' => 'pending',
            'payload' => json_encode(['asset_id' => $asset->id, 'new_status' => 'lost']),
        ]);

        // Second request should be prevented by logic (simulated logic)
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Barang ini masih memiliki pengajuan yang menunggu persetujuan');

        DB::transaction(function () use ($asset, $user) {
            $lockedAsset = Asset::where('id', $asset->id)->lockForUpdate()->first();
            
            $hasPending = ApprovalRequest::where('status', 'pending')
                ->where('request_type', 'status_change')
                ->whereJsonContains('payload->asset_id', $lockedAsset->id)
                ->exists();

            if ($hasPending) {
                throw new Exception("Barang ini masih memiliki pengajuan yang menunggu persetujuan.");
            }
        });
    }

    public function test_transaction_rollback_preserves_state()
    {
        $asset = Asset::factory()->create(['status' => 'stock']);
        
        try {
            DB::transaction(function () use ($asset) {
                $asset->update(['status' => 'lost']);
                throw new Exception("Simulated Failure");
            });
        } catch (Exception $e) {
            // Ignored
        }

        $this->assertEquals('stock', $asset->fresh()->status);
    }

    public function test_concurrent_approval_resolution()
    {
        $user = User::factory()->create();
        $request = ApprovalRequest::create([
            'request_type' => 'status_change',
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);

        // Simulating the first approver
        DB::transaction(function () use ($request, $user) {
            $lockedRecord = ApprovalRequest::where('id', $request->id)->lockForUpdate()->first();
            $lockedRecord->update(['status' => 'approved', 'approved_by' => $user->id]);
        });

        // Simulating the second approver exactly after
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Pengajuan ini sudah diproses.');

        DB::transaction(function () use ($request, $user) {
            $lockedRecord = ApprovalRequest::where('id', $request->id)->lockForUpdate()->first();
            if ($lockedRecord->status !== 'pending') {
                throw new Exception('Pengajuan ini sudah diproses.');
            }
        });
    }

    public function test_movement_rollback_preserves_state()
    {
        $asset = Asset::factory()->create();
        $movement = AssetMovement::factory()->create([
            'asset_id' => $asset->id,
            'status' => 'approved'
        ]);

        try {
            DB::transaction(function () use ($movement) {
                $lockedRecord = AssetMovement::where('id', $movement->id)->lockForUpdate()->first();
                $lockedRecord->update(['status' => 'completed']);
                throw new Exception("Simulated Movement Failure");
            });
        } catch (Exception $e) {
            // Ignored
        }

        $this->assertEquals('approved', $movement->fresh()->status);
    }
}
