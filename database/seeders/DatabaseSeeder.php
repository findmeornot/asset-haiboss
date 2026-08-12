<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Administrator',
                'password' => bcrypt('password'),
            ]
        );

        $adminExample = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Example',
                'password' => bcrypt('password'),
            ]
        );

        $this->call(RolePermissionSeeder::class);
        $this->call(ClassificationSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(DummyDataSeeder::class);
    }
}
