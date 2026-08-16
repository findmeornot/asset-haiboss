<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Define all permissions exactly as required
        $permissions = [
            // Asset
            'assets.view', 'assets.create', 'assets.update', 'assets.delete',
            // Master Data
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'campuses.view', 'campuses.create', 'campuses.update', 'campuses.delete',
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            // Movement
            'movements.view', 'movements.create', 'movements.approve', 'movements.reject', 'movements.complete',
            // Status
            'status.view', 'status.update', 'status.approve',
            // Stock Opname
            'stock_opname.view', 'stock_opname.create', 'stock_opname.update', 'stock_opname.complete',
            // QR / Barcode
            'asset_qr.view', 'asset_qr.generate', 'asset_scanner.use',
            // Financial
            'financial.view', 'financial.update', 'financial.export',
            // Reports
            'reports.view', 'reports.export',
            // Audit
            'audit.view', 'audit.export',
            // User Administration
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'permissions.view', 'permissions.manage',
            // Panel Access
            'panel.admin', 'panel.inventory',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define Roles and their specific permissions
        $roles = [
            'Superadmin' => [], // Bypass via User model
            
            'Tim Inventaris' => [
                'panel.inventory',
                'assets.view', 'assets.create', 'assets.update',
                'categories.view', 'categories.create', 'categories.update',
                'campuses.view', 'campuses.create', 'campuses.update',
                'locations.view', 'locations.create', 'locations.update',
                'employees.view', 'employees.create', 'employees.update',
                'movements.view', 'movements.create',
                'status.view', 'status.update',
                'stock_opname.view', 'stock_opname.create', 'stock_opname.update', 'stock_opname.complete',
                'asset_qr.view', 'asset_qr.generate', 'asset_scanner.use',
                'reports.view', 'reports.export',
                'audit.view',
            ],

            'Finance' => [
                'panel.inventory',
                'assets.view',
                'financial.view', 'financial.export',
                'reports.view', 'reports.export',
            ],

            'Approver' => [
                'panel.inventory',
                'assets.view',
                'movements.view', 'movements.approve', 'movements.reject', 'movements.complete',
                'status.view', 'status.approve',
                'reports.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            
            if (!empty($rolePermissions)) {
                $permissionIds = Permission::whereIn('name', $rolePermissions)->pluck('id');
                $role->permissions()->sync($permissionIds);
            }
        }

        // Assign Superadmin role to existing Superadmin user (assumed first user)
        $user = User::first();
        if ($user) {
            $superadminRole = Role::where('name', 'Superadmin')->first();
            if (!$user->roles()->where('role_id', $superadminRole->id)->exists()) {
                $user->roles()->attach($superadminRole->id);
            }
        }
    }
}
