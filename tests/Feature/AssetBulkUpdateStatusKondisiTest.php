<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AssetBulkUpdateStatusKondisiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $role = Role::firstOrCreate(['name' => 'superadmin']);
        
        $this->superadmin = User::factory()->create();
        $this->superadmin->roles()->attach([$role->id]);

        $this->user = User::factory()->create();
    }

    public function test_superadmin_can_bulk_update_status()
    {
        $assets = Asset::factory()->count(3)->create(['status' => 'active']);

        $this->actingAs($this->superadmin);

        \Livewire\Livewire::test(\App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets::class)
            ->callTableBulkAction('changeStatusBulk', $assets, [
                'new_status' => 'maintenance',
                'reason' => 'Perbaikan rutin'
            ])
            ->assertSuccessful();

        foreach ($assets as $asset) {
            $this->assertEquals('maintenance', $asset->fresh()->status);
            
            // Check that the reason was logged in audit_logs
            $log = AuditLog::where('loggable_type', Asset::class)
                ->where('loggable_id', $asset->id)
                ->where('action', 'status_change')
                ->latest()
                ->first();
                
            $this->assertNotNull($log);
            $this->assertEquals('Perbaikan rutin', $log->reason);
        }
    }

    public function test_superadmin_can_bulk_update_kondisi()
    {
        $assets = Asset::factory()->count(3)->create(['kondisi' => 'good']);

        $this->actingAs($this->superadmin);

        \Livewire\Livewire::test(\App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets::class)
            ->callTableBulkAction('changeKondisiBulk', $assets, [
                'new_kondisi' => 'minor_damage',
                'reason' => 'Lecet pemakaian'
            ])
            ->assertSuccessful();

        foreach ($assets as $asset) {
            $this->assertEquals('minor_damage', $asset->fresh()->kondisi);
            
            $log = AuditLog::where('loggable_type', Asset::class)
                ->where('loggable_id', $asset->id)
                ->where('action', 'status_change')
                ->latest()
                ->first();
                
            $this->assertNotNull($log);
            $this->assertEquals('Lecet pemakaian', $log->reason);
        }
    }

    public function test_non_superadmin_cannot_see_bulk_update_status()
    {
        $this->actingAs($this->user);

        \Livewire\Livewire::test(\App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets::class)
            ->assertTableBulkActionDoesNotExist('changeStatusBulk');
    }

    public function test_non_superadmin_cannot_see_bulk_update_kondisi()
    {
        $this->actingAs($this->user);

        \Livewire\Livewire::test(\App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets::class)
            ->assertTableBulkActionDoesNotExist('changeKondisiBulk');
    }

    public function test_bulk_status_to_lost_creates_approval_request()
    {
        $assets = Asset::factory()->count(2)->create(['status' => 'active']);

        $this->actingAs($this->superadmin);

        \Livewire\Livewire::test(\App\Filament\Inventory\Resources\AssetResource\Pages\ListAssets::class)
            ->callTableBulkAction('changeStatusBulk', $assets, [
                'new_status' => 'lost',
                'reason' => 'Kehilangan'
            ])
            ->assertSuccessful();

        // Assets should remain active
        foreach ($assets as $asset) {
            $this->assertEquals('active', $asset->fresh()->status);
            
            $this->assertTrue(ApprovalRequest::where('request_type', 'status_change')
                ->where('status', 'pending')
                ->whereJsonContains('payload->asset_id', $asset->id)
                ->exists());
        }
    }
}