<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use RuntimeException;
use Tests\TestCase;

class AuditTrailHardeningTest extends TestCase
{
    use DatabaseTruncation;

    public function test_audit_trail_is_immutable_and_cannot_be_updated()
    {
        $log = AuditLog::create([
            'action' => AuditAction::CREATED,
            'metadata' => ['key' => 'value'],
            'created_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit logs cannot be modified.');

        $log->update(['action' => AuditAction::DELETED]);
    }

    public function test_audit_trail_is_immutable_and_cannot_be_deleted()
    {
        $log = AuditLog::create([
            'action' => AuditAction::CREATED,
            'created_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit logs cannot be deleted.');

        $log->delete();
    }

    public function test_sensitive_fields_are_filtered_from_old_and_new_values()
    {
        $old = [
            'name' => 'John Doe',
            'password' => 'secret_password_123',
            'api_key' => 'my-secret-key',
        ];
        $new = [
            'name' => 'John Doe Updated',
            'password' => 'new_secret_password_123',
            'api_key' => 'my-new-secret-key',
        ];

        $log = AuditLogger::log(
            AuditAction::USER_UPDATED,
            null,
            $old,
            $new
        );

        $this->assertArrayHasKey('name', $log->old_values);
        $this->assertArrayNotHasKey('password', $log->old_values);
        $this->assertArrayNotHasKey('api_key', $log->old_values);

        $this->assertArrayHasKey('name', $log->new_values);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('api_key', $log->new_values);
    }

    public function test_audit_handles_null_metadata_gracefully_in_model()
    {
        \DB::table('audit_logs')->insert([
            'id' => 9999,
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'action' => 'login',
            'metadata' => null, // Test null JSON
            'created_at' => now()
        ]);

        $log = AuditLog::find(9999);
        $this->assertNotNull($log);
        $this->assertEquals('login', $log->action->value);
        $this->assertNull($log->metadata);
    }

    public function test_audit_logs_unauthenticated_requests_safely()
    {
        // When there is no authenticated user, user_id should be null, not cause exception
        $log = AuditLogger::log(AuditAction::LOGIN_FAILED, null, null, null, 'Invalid credentials');
        
        $this->assertNull($log->user_id);
        $this->assertEquals(AuditAction::LOGIN_FAILED, $log->action);
    }
}
