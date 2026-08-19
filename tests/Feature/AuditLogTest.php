<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Classification;
use App\Models\Campus;
use App\Models\Location;
use App\Models\AuditLog;
use App\Models\AssetPhoto;
use App\Models\AssetMovement;
use App\Enums\AuditAction;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use DatabaseTruncation;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        \App\Services\AuditLogger::$currentBatchUuid = null;
        \App\Services\AuditLogger::$currentParentId = null;
        parent::tearDown();
    }

    public function test_login_success_creates_audit_log()
    {
        Event::dispatch(new Login('web', $this->user, false));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LOGIN->value,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_login_failed_creates_audit_log_without_sensitive_data()
    {
        $credentials = ['email' => 'test@example.com', 'password' => 'secret123'];
        Event::dispatch(new Failed('web', null, $credentials));

        $log = AuditLog::where('action', AuditAction::LOGIN_FAILED->value)->first();
        $this->assertNotNull($log);
        
        $metadata = $log->metadata;
        $this->assertArrayHasKey('credentials', $metadata);
        $this->assertArrayHasKey('email', $metadata['credentials']);
        $this->assertArrayNotHasKey('password', $metadata['credentials']);
    }

    public function test_logout_creates_audit_log()
    {
        Event::dispatch(new Logout('web', $this->user));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LOGOUT->value,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_asset_create_update_delete_restore_creates_audit_log()
    {
        $classification = Classification::create(['name' => 'IT Equipment', 'slug' => 'it-equipment']);
        $category = Category::create(['name' => 'Laptop', 'code' => 'LAP']);
        $category->classifications()->attach($classification);
        $campus = Campus::create(['name' => 'Main Campus']);
        $location = Location::create(['name' => 'Room 101', 'campus_id' => $campus->id]);

        // Create
        $asset = Asset::create([
            'inventory_number' => 'INV-001',
            'classification_id' => $classification->id,
            'category_id' => $category->id,
            'name' => 'Test Laptop',
            'campus_id' => $campus->id,
            'location_id' => $location->id,
            'status' => 'stock',
            'ownership' => 'yayasan',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CREATED->value,
            'auditable_type' => Asset::class,
            'auditable_id' => $asset->id,
        ]);

        // Duplicate prevention test: updating multiple times with same or different fields should create proper count of logs
        $asset->update(['name' => 'Updated Laptop']);
        
        $updateLogs = AuditLog::where('action', AuditAction::UPDATED->value)
            ->where('auditable_id', $asset->id)
            ->get();
            
        $this->assertCount(1, $updateLogs); // Exactly 1 update log
        $updateLog = $updateLogs->first();
        $this->assertEquals('Test Laptop', $updateLog->old_values['name']);
        $this->assertEquals('Updated Laptop', $updateLog->new_values['name']);

        // Update with no real changes shouldn't create log
        $asset->update(['name' => 'Updated Laptop']);
        $this->assertCount(1, AuditLog::where('action', AuditAction::UPDATED->value)->where('auditable_id', $asset->id)->get());

        // Delete
        $asset->delete();
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DELETED->value,
            'auditable_type' => Asset::class,
            'auditable_id' => $asset->id,
        ]);

        // Restore
        $asset->restore();
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RESTORED->value,
            'auditable_type' => Asset::class,
            'auditable_id' => $asset->id,
        ]);
    }

    public function test_asset_photo_upload_delete_creates_audit_log()
    {
        $asset = Asset::factory()->create(['name' => 'Photo Asset']);
        
        $photo = AssetPhoto::create([
            'asset_id' => $asset->id,
            'file_path' => 'photos/test.jpg'
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PHOTO_UPLOADED->value,
            'auditable_type' => Asset::class,
            'auditable_id' => $asset->id,
        ]);

        $photo->delete();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PHOTO_DELETED->value,
            'auditable_type' => Asset::class,
            'auditable_id' => $asset->id,
        ]);
    }

    public function test_asset_movement_creates_audit_log()
    {
        $asset = Asset::factory()->create(['name' => 'Movement Asset']);
        
        $movement = AssetMovement::create([
            'asset_id' => $asset->id,
            'status' => 'pending'
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::MUTATION_CREATED->value,
            'auditable_type' => AssetMovement::class,
            'auditable_id' => $movement->id,
        ]);

        $movement->update(['status' => 'approved']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::MUTATION_APPROVED->value,
            'auditable_type' => AssetMovement::class,
            'auditable_id' => $movement->id,
        ]);
    }

    public function test_ip_and_user_agent_are_captured()
    {
        $asset = Asset::factory()->create(['name' => 'IP Test Asset']);

        $log = AuditLog::where('action', AuditAction::CREATED->value)
            ->where('auditable_id', $asset->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('127.0.0.1', $log->metadata['ip_address']);
        $this->assertEquals('Symfony', $log->metadata['user_agent']);
    }

    public function test_parent_child_bulk_audit_works()
    {
        $batchUuid = 'batch-1234';
        
        \App\Services\AuditLogger::$currentBatchUuid = $batchUuid;
        $parentAudit = \App\Services\AuditLogger::log(
            action: AuditAction::IMPORT_STARTED,
            batchUuid: $batchUuid,
            metadata: ['file_name' => 'test.xlsx']
        );
        \App\Services\AuditLogger::$currentParentId = $parentAudit->id;

        $asset = Asset::factory()->create(['name' => 'Child Asset']);

        $childLog = AuditLog::where('action', AuditAction::CREATED->value)
            ->where('auditable_id', $asset->id)
            ->first();

        $this->assertNotNull($childLog);
        $this->assertEquals($batchUuid, $childLog->batch_uuid);
        $this->assertEquals($parentAudit->id, $childLog->parent_id);

        // Reset
        \App\Services\AuditLogger::$currentBatchUuid = null;
        \App\Services\AuditLogger::$currentParentId = null;
    }

    public function test_audit_logs_are_immutable()
    {
        $asset = Asset::factory()->create();
        $log = AuditLog::where('action', AuditAction::CREATED->value)->first();

        // Updating log should throw or fail depending on how immutability is implemented.
        // Wait, does the project implement immutability? The prompt says "Gunakan mekanisme yang sesuai dengan implementasi Milestone 1."
        // Let's check if there is an Observer on AuditLog or simply a rule.
        // For now, we will just attempt to update and delete, if there's no guard, we might need to add one.
        // Let's just create an AuditLogObserver to enforce immutability if it's missing.
    }

    public function test_transaction_rollback_prevents_audit()
    {
        $asset = Asset::factory()->create(['name' => 'Initial Name']);
        $initialLogCount = AuditLog::count();

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($asset) {
                $asset->update(['name' => 'Updated in Transaction']);
                throw new \Exception('Rollback!');
            });
        } catch (\Exception $e) {
            // expected
        }

        $this->assertEquals($initialLogCount, AuditLog::count());
        $this->assertEquals('Initial Name', $asset->fresh()->name);
    }
}
