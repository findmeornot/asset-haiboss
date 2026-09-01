<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Campus;
use App\Models\Location;
use App\Models\Employee;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Enums\AuditAction;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class ApprovalAuditTest extends TestCase
{
    use DatabaseTruncation;

    protected User $requester;
    protected User $approver;
    protected Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->requester = User::factory()->create(['name' => 'User A']);
        $this->approver = User::factory()->create(['name' => 'User B']);
        
        $campus = Campus::create(['name' => 'Gedung A']);
        $location = Location::create(['name' => 'Ruang A', 'campus_id' => $campus->id]);
        $pic = Employee::create(['name' => 'Budi']);
        
        $classification = Classification::create(['name' => 'IT', 'slug' => 'it']);
        $category = Category::create(['name' => 'Laptop', 'code' => 'LAP']);
        $category->classifications()->attach($classification);

        $this->asset = Asset::create([
            'inventory_number' => 'INV-001',
            'classification_id' => $classification->id,
            'category_id' => $category->id,
            'name' => 'Test Laptop',
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'pic_id' => $pic->id,
            'status' => 'stock',
            'ownership' => 'yayasan',
        ]);

        \App\Services\AuditLogger::$currentBatchUuid = null;
        \App\Services\AuditLogger::$currentParentId = null;
    }

    public function test_approval_request_approved_with_parent_child_relationship()
    {
        $this->actingAs($this->approver);

        $approvalRequest = ApprovalRequest::create([
            'request_type' => 'status_change',
            'requested_by' => $this->requester->id,
            'reason' => 'Perlu dipinjam',
            'payload' => json_encode([
                'asset_id' => $this->asset->id,
                'new_status' => 'in_use'
            ]),
            'status' => 'pending'
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($approvalRequest) {
            $approvalRequest->update([
                'status' => 'approved',
                'approved_by' => $this->approver->id,
                'approved_at' => now(),
            ]);
            
            $payload = json_decode($approvalRequest->payload, true);

            $parentAudit = \App\Services\AuditLogger::log(
                action: AuditAction::MUTATION_APPROVED,
                model: $approvalRequest,
                old: ['status' => 'pending'],
                new: ['status' => 'approved'],
                metadata: ['payload' => $payload]
            );

            \App\Services\AuditLogger::$currentParentId = $parentAudit->id;

            if ($approvalRequest->request_type === 'status_change' && isset($payload['asset_id']) && isset($payload['new_status'])) {
                $asset = Asset::where('id', $payload['asset_id'])->lockForUpdate()->first();
                if ($asset) {
                    request()->merge(['status_change_reason' => 'Approved: ' . $approvalRequest->reason]);
                    $asset->update(['status' => $payload['new_status']]);
                }
            }

            \App\Services\AuditLogger::$currentParentId = null;
        });

        $parentLog = AuditLog::where('action', AuditAction::MUTATION_APPROVED->value)
            ->where('auditable_type', ApprovalRequest::class)
            ->where('auditable_id', $approvalRequest->id)
            ->first();

        $this->assertNotNull($parentLog);

        $childLogs = AuditLog::where('parent_id', $parentLog->id)->get();
        $this->assertNotEmpty($childLogs);

        $statusLog = $childLogs->firstWhere('action', AuditAction::STATUS_CHANGE->value);
        $this->assertNotNull($statusLog);
        $this->assertEquals('stock', $statusLog->old_values['status']);
        $this->assertEquals('in_use', $statusLog->new_values['status']);
        $this->assertEquals('Approved: Perlu dipinjam', $statusLog->reason);
        
        // Ensure no duplicate root audit logs for status change
        $rootStatusLogs = AuditLog::whereNull('parent_id')
            ->where('action', AuditAction::STATUS_CHANGE->value)
            ->where('auditable_id', $this->asset->id)
            ->count();
            
        $this->assertEquals(0, $rootStatusLogs);
    }
}
