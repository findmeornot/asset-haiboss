<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Make auditable nullable for system activities (like login/logout)
            $table->string('auditable_type')->nullable()->change();
            $table->unsignedBigInteger('auditable_id')->nullable()->change();
            
            // Parent-child relationship for bulk operations or mutations
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('audit_logs')->restrictOnDelete();
            
            // Batch identifier
            $table->uuid('batch_uuid')->nullable()->after('parent_id')->index();
            
            // Additional metadata
            $table->json('metadata')->nullable()->after('new_values');
            
            // Add index for action to improve filtering
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['action']);
            $table->dropIndex(['batch_uuid']);
            
            $table->dropColumn(['parent_id', 'batch_uuid', 'metadata']);
            
            $table->string('auditable_type')->nullable(false)->change();
            $table->unsignedBigInteger('auditable_id')->nullable(false)->change();
        });
    }
};
