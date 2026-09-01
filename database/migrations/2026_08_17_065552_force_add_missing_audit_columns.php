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
            if (!Schema::hasColumn('audit_logs', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('audit_logs')->restrictOnDelete();
            }
            if (!Schema::hasColumn('audit_logs', 'batch_uuid')) {
                $table->uuid('batch_uuid')->nullable()->after('parent_id')->index();
            }
            if (!Schema::hasColumn('audit_logs', 'metadata')) {
                $table->json('metadata')->nullable()->after('new_values');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // No drop needed for this safe migration
        });
    }
};
