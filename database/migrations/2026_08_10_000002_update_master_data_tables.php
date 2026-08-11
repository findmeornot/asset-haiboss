<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
            $table->boolean('active')->default(true)->after('description');
        });

        Schema::table('campuses', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
            $table->boolean('active')->default(true)->after('address');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('type'); // notes already exists, but user asked for description
            $table->boolean('active')->default(true)->after('description');
        });

        Schema::table('employees', function (Blueprint $table) {
            // we already have employee_number, we can add employee_code or rename. Let's add employee_code
            $table->string('employee_code')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->after('department');
            $table->string('email')->nullable()->after('phone');
            $table->boolean('active')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['code', 'active']);
        });

        Schema::table('campuses', function (Blueprint $table) {
            $table->dropColumn(['code', 'active']);
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['code', 'description', 'active']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['employee_code', 'phone', 'email', 'active']);
        });
    }
};
