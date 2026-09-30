<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('stock.export', 'web');
        Role::query()->whereIn('name', ['Petugas Gudang A', 'Petugas Pemeriksaan'])
            ->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Existing role assignments may have been changed manually after migration.
    }
};
