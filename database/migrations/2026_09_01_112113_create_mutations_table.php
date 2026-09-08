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
        Schema::create('mutations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('mutation_number')->unique();
            $table->string('type'); // asset, inventory, consumable
            
            // Source
            $table->foreignId('source_campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->foreignId('source_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('source_pic_id')->nullable()->constrained('employees')->nullOnDelete();
            
            // Destination
            $table->foreignId('destination_campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('destination_pic_id')->nullable()->constrained('employees')->nullOnDelete();
            
            // Workflow
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('mutation_date')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, completed, cancelled
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutations');
    }
};
