<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Role;
use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class AuditTrailExportTest extends TestCase
{
    use DatabaseTruncation;

    protected $superadmin;
    protected $staff;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The intl extension is not available.');
        }

        $superadminRole = Role::firstOrCreate(['name' => 'Superadmin']);
        $staffRole = Role::firstOrCreate(['name' => 'Staff']);

        $this->superadmin = User::factory()->create();
        $this->superadmin->roles()->attach($superadminRole);

        $this->staff = User::factory()->create();
        $this->staff->roles()->attach($staffRole);
    }

    public function test_superadmin_can_export_excel_and_it_logs_activity()
    {
        $this->actingAs($this->superadmin);

        AuditLog::create([
            'action' => AuditAction::CREATED,
            'user_id' => $this->staff->id,
            'metadata' => ['ip_address' => '127.0.0.1'],
            'created_at' => now(),
        ]);

        $initialAuditCount = AuditLog::count();

        Livewire::test(ListAuditLogs::class)
            ->callTableAction('export_excel');

        // Check if an EXPORT_EXCEL activity was logged
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::EXPORT_EXCEL->value,
            'user_id' => $this->superadmin->id,
        ]);
        
        $this->assertEquals($initialAuditCount + 1, AuditLog::count());
    }

    public function test_superadmin_can_export_pdf_and_it_logs_activity()
    {
        $this->actingAs($this->superadmin);

        AuditLog::create([
            'action' => AuditAction::CREATED,
            'user_id' => $this->staff->id,
            'metadata' => ['ip_address' => '127.0.0.1'],
            'created_at' => now(),
        ]);

        $initialAuditCount = AuditLog::count();

        Livewire::test(ListAuditLogs::class)
            ->callTableAction('export_pdf')
            ->assertFileDownloaded();

        // Check if an EXPORT_PDF activity was logged
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::EXPORT_PDF->value,
            'user_id' => $this->superadmin->id,
        ]);
        
        $this->assertEquals($initialAuditCount + 1, AuditLog::count());
    }

    public function test_non_superadmin_cannot_export_excel_or_pdf()
    {
        $this->actingAs($this->staff);

        Livewire::test(ListAuditLogs::class)
            ->assertTableActionHidden('export_excel')
            ->assertTableActionHidden('export_pdf');
    }

    public function test_export_follows_filters()
    {
        $this->actingAs($this->superadmin);

        AuditLog::create([
            'action' => AuditAction::CREATED,
            'user_id' => $this->staff->id,
            'created_at' => now()->subDays(5),
        ]);
        
        AuditLog::create([
            'action' => AuditAction::DELETED,
            'user_id' => $this->staff->id,
            'created_at' => now(),
        ]);

        // When exporting, it should log the number of records filtered
        Livewire::test(ListAuditLogs::class)
            ->filterTable('action', AuditAction::DELETED->value)
            ->callTableAction('export_excel');

        // Check the log to see if record_count is 1 (only the DELETED record)
        $log = AuditLog::where('action', AuditAction::EXPORT_EXCEL)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(1, $log->metadata['record_count']);
    }

    public function test_export_does_not_modify_audits()
    {
        $this->actingAs($this->superadmin);

        $audit = AuditLog::create([
            'action' => AuditAction::CREATED,
            'user_id' => $this->staff->id,
            'metadata' => ['test' => 'original'],
            'created_at' => now(),
        ]);

        Livewire::test(ListAuditLogs::class)
            ->callTableAction('export_excel');

        $this->assertDatabaseHas('audit_logs', [
            'id' => $audit->id,
            'action' => AuditAction::CREATED->value,
        ]);
        $audit->refresh();
        $this->assertEquals('original', $audit->metadata['test']);
    }
}
