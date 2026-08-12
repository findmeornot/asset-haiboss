<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('category_classification', function (Blueprint $table) {
            $table->foreignId('classification_id')->constrained('classifications')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['classification_id', 'category_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('classification_id')->nullable()->after('name')
                ->constrained('classifications')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classification_id');
        });

        Schema::dropIfExists('category_classification');
        Schema::dropIfExists('classifications');
    }
};
