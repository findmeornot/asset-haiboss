<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Campus;
use App\Models\Location;
use App\Models\Employee;
use App\Models\AssetMovement;
use App\Models\AuditLog;
use App\Enums\AuditAction;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class MutationAuditTest extends TestCase
{
    use DatabaseTruncation;

    protected User $user;
    protected User $approver;
    protected Asset $asset;
    protected Campus $campusA;
    protected Campus $campusB;
    protected Location $locA;
    protected Location $locB;
    protected Employee $picA;
    protected Employee $picB;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['name' => 'User A']);
        $this->approver = User::factory()->create(['name' => 'User B']);
        
        $this->campusA = Campus::create(['name' => 'Gedung A']);
        $this->campusB = Campus::create(['name' => 'Gedung B']);
        $this->locA = Location::create(['name' => 'Ruang A', 'campus_id' => $this->campusA->id]);
        $this->locB = Location::create(['name' => 'Ruang B', 'campus_id' => $this->campusB->id]);
        $this->picA = Employee::create(['name' => 'Budi']);
        $this->picB = Employee::create(['name' => 'Andi']);
        
        $classification = Classification::create(['name' => 'IT', 'slug' => 'it']);
        $category = Category::create(['name' => 'Laptop', 'code' => 'LAP']);
        $category->classifications()->attach($classification);

        $this->asset = Asset::create([
            'inventory_number' => 'INV-001',
            'classification_id' => $classification->id,
            'category_id' => $category->id,
            'name' => 'Test Laptop',
            'campus_id' => $this->campusA->id,
            'location_id' => $this->locA->id,
            'pic_id' => $this->picA->id,
            'status' => 'stock',
            'ownership' => 'yayasan',
        ]);

        \App\Services\AuditLogger::$currentBatchUuid = null;
        \App\Services\AuditLogger::$currentParentId = null;
    }

    public function test_mutation_created_and_snapshot()
    {
        $this->actingAs($this->user);

        $movement = AssetMovement::create([
            'asset_id' => $this->asset->id,
            'source_campus_id' => $this->campusA->id,
            'source_location_id' => $this->locA->id,
            'source_pic_id' => $this->picA->id,
            'destination_campus_id' => $this->campusB->id,
            'destination_location_id' => $this->locB->id,
            'destination_pic_id' => $this->picB->id,
            'requested_by' => $this->user->id,
            'reason' => 'Pindah tugas',
            'status' => 'pending',
        ]);

        $log = AuditLog::where('action', AuditAction::MUTATION_CREATED->value)
            ->where('auditable_type', AssetMovement::class)
            ->where('auditable_id', $movement->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('pending', $log->metadata['snapshot']['status']);
        $this->assertEquals('Ruang A', $log->metadata['snapshot']['source_location']);
        $this->assertEquals('Budi', $log->metadata['snapshot']['source_pic']);
    }

    public function test_mutation_approval()
    {
        $this->actingAs($this->approver);

        $movement = AssetMovement::create([
            'asset_id' => $this->asset->id,
            'source_campus_id' => $this->campusA->id,
            'source_location_id' => $this->locA->id,
            'source_pic_id' => $this->picA->id,
            'destination_campus_id' => $this->campusB->id,
            'destination_location_id' => $this->locB->id,
            'destination_pic_id' => $this->picB->id,
            'requested_by' => $this->user->id,
            'reason' => 'Pindah tugas',
            'status' => 'pending',
        ]);

        $movement->update([
            'status' => 'approved',
            'approved_by' => $this->approver->id,
        ]);

        $log = AuditLog::where('action', AuditAction::MUTATION_APPROVED->value)
            ->where('auditable_id', $movement->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('pending', $log->old_values['status']);
        $this->assertEquals('approved', $log->new_values['status']);
        $this->assertEquals('User A', $log->metadata['snapshot']['requester']);
        $this->assertEquals('User B', $log->metadata['snapshot']['approver']);
    }

    public function test_mutation_rejection_with_reason()
    {
        $this->actingAs($this->approver);

        $movement = AssetMovement::create([
            'asset_id' => $this->asset->id,
            'source_campus_id' => $this->campusA->id,
            'source_location_id' => $this->locA->id,
            'source_pic_id' => $this->picA->id,
            'destination_campus_id' => $this->campusB->id,
            'destination_location_id' => $this->locB->id,
            'destination_pic_id' => $this->picB->id,
            'requested_by' => $this->user->id,
            'reason' => 'Pindah tugas',
            'status' => 'pending',
        ]);

        request()->merge(['reject_reason' => 'Barang masih digunakan oleh unit asal.']);

        $movement->update([
            'status' => 'rejected',
            'approved_by' => $this->approver->id,
        ]);

        $log = AuditLog::where('action', AuditAction::MUTATION_REJECTED->value)
            ->where('auditable_id', $movement->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Barang masih digunakan oleh unit asal.', $log->reason);
        $this->assertEquals('User B', $log->metadata['snapshot']['approver']);
    }

    public function test_mutation_completed_parent_child_relationship()
    {
        $this->actingAs($this->approver);

        $movement = AssetMovement::create([
            'asset_id' => $this->asset->id,
            'source_campus_id' => $this->campusA->id,
            'source_location_id' => $this->locA->id,
            'source_pic_id' => $this->picA->id,
            'destination_campus_id' => $this->campusB->id,
            'destination_location_id' => $this->locB->id,
            'destination_pic_id' => $this->picB->id,
            'requested_by' => $this->user->id,
            'reason' => 'Pindah tugas',
            'status' => 'approved',
            'approved_by' => $this->approver->id,
        ]);

        // Simulating the Action transaction for completion
        \Illuminate\Support\Facades\DB::transaction(function () use ($movement) {
            $movement->update([
                'status' => 'completed',
                'movement_date' => now(),
            ]);

            $parentLog = AuditLog::where('auditable_type', AssetMovement::class)
                ->where('auditable_id', $movement->id)
                ->where('action', 'mutation_completed')
                ->latest('id')
                ->first();

            \App\Services\AuditLogger::$currentParentId = $parentLog->id;

            $this->asset->update([
                'campus_id' => $movement->destination_campus_id,
                'location_id' => $movement->destination_location_id,
                'pic_id' => $movement->destination_pic_id,
            ]);

            \App\Services\AuditLogger::$currentParentId = null;
        });

        $parentLog = AuditLog::where('action', AuditAction::MUTATION_COMPLETED->value)
            ->where('auditable_id', $movement->id)
            ->first();

        $this->assertNotNull($parentLog);

        $childLogs = AuditLog::where('parent_id', $parentLog->id)->get();
        $this->assertNotEmpty($childLogs);

        $locationLog = $childLogs->firstWhere('action', AuditAction::LOCATION_CHANGE->value);
        $this->assertNotNull($locationLog);
        $this->assertEquals($this->locA->id, $locationLog->old_values['location_id']);
        $this->assertEquals($this->locB->id, $locationLog->new_values['location_id']);
        $this->assertEquals($this->picA->id, $locationLog->old_values['pic_id']);
        $this->assertEquals($this->picB->id, $locationLog->new_values['pic_id']);
        
        // Ensure no duplicate root audit logs for location change
        $rootLocationLogs = AuditLog::whereNull('parent_id')
            ->where('action', AuditAction::LOCATION_CHANGE->value)
            ->where('auditable_id', $this->asset->id)
            ->count();
            
        $this->assertEquals(0, $rootLocationLogs);
    }

    public function test_snapshot_preserves_historical_data()
    {
        $this->actingAs($this->user);

        $movement = AssetMovement::create([
            'asset_id' => $this->asset->id,
            'source_campus_id' => $this->campusA->id,
            'source_location_id' => $this->locA->id,
            'source_pic_id' => $this->picA->id,
            'destination_campus_id' => $this->campusB->id,
            'destination_location_id' => $this->locB->id,
            'destination_pic_id' => $this->picB->id,
            'requested_by' => $this->user->id,
            'reason' => 'Pindah tugas',
            'status' => 'pending',
        ]);

        $log = AuditLog::where('action', AuditAction::MUTATION_CREATED->value)->first();
        
        // Even if the location name changes later
        $this->locA->update(['name' => 'Ruang Berubah']);
        
        // The snapshot remains the original name
        $this->assertEquals('Ruang A', $log->metadata['snapshot']['source_location']);
    }
    
    public function test_rollback_prevents_orphan_success_audit()
    {
        $this->actingAs($this->approver);

        $movement = AssetMovement::create([
            'asset_id' => $this->asset->id,
            'source_campus_id' => $this->campusA->id,
            'source_location_id' => $this->locA->id,
            'source_pic_id' => $this->picA->id,
            'destination_campus_id' => $this->campusB->id,
            'destination_location_id' => $this->locB->id,
            'destination_pic_id' => $this->picB->id,
            'requested_by' => $this->user->id,
            'reason' => 'Pindah tugas',
            'status' => 'pending',
        ]);
        
        $initialLogCount = AuditLog::count();

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($movement) {
                $movement->update([
                    'status' => 'approved',
                    'approved_by' => $this->approver->id,
                ]);
                throw new \Exception('Rollback!');
            });
        } catch (\Exception $e) {
            // expected
        }

        $this->assertEquals($initialLogCount, AuditLog::count());
        $this->assertEquals('pending', $movement->fresh()->status);
    }
}
