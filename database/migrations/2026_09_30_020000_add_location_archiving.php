<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_types', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
        });
        Schema::table('locations', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
        });

        $permission = Permission::findOrCreate('stock.export', 'web');
        Role::query()->whereIn('name', ['Administrator', 'Petugas Gudang B'])->where('guard_name', 'web')
            ->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
        Schema::table('location_types', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
