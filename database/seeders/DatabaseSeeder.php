<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $names = [
            'dashboard.view', 'items.view', 'items.manage', 'items.delete-master', 'stock.adjust', 'stock.export',
            'loans.create', 'loans.view-all', 'loans.approve', 'loans.handover',
            'requests.create', 'requests.view-all', 'requests.approve', 'requests.fulfill',
            'checks.view', 'checks.perform', 'access.manage', 'locations.manage',
        ];
        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = Role::findOrCreate('Administrator', 'web');
        $admin->syncPermissions($names);
        $defaults = [
            'Petugas Gudang A' => ['dashboard.view', 'items.view', 'stock.export', 'loans.create', 'loans.view-all', 'loans.approve', 'loans.handover'],
            'Petugas Gudang B' => ['dashboard.view', 'items.view', 'stock.adjust', 'stock.export', 'requests.create', 'requests.view-all', 'requests.approve', 'requests.fulfill'],
            'Pemohon' => ['dashboard.view', 'items.view', 'loans.create', 'requests.create'],
            'Petugas Pemeriksaan' => ['dashboard.view', 'items.view', 'stock.export', 'checks.view', 'checks.perform'],
        ];
        foreach ($defaults as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($permissions);
            }
        }

        $email = config('inventory.initial_admin_email');
        $password = config('inventory.initial_admin_password');
        if ($email && $password && ! User::where('email', $email)->exists()) {
            User::create(['name' => 'Administrator', 'email' => $email, 'password' => Hash::make($password)])->assignRole($admin);
        }
    }
}
