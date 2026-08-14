<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    protected $tables = [
        'users',
        'roles',
        'permissions',
        'assets',
        'categories',
        'campuses',
        'locations',
        'employees',
        'asset_movements',
        'stock_opnames',
        'approval_requests',
        'audit_logs',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->ulid('ulid')->nullable()->after('id');
                });
                
                // Backfill existing records
                $records = DB::table($tableName)->get();
                foreach ($records as $record) {
                    DB::table($tableName)->where('id', $record->id)->update([
                        'ulid' => (string) Str::ulid(),
                    ]);
                }

                // Make ulid unique and not nullable
                Schema::table($tableName, function (Blueprint $table) {
                    $table->ulid('ulid')->nullable(false)->unique()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('all_models', function (Blueprint $table) {
            //
        });
    }
};
