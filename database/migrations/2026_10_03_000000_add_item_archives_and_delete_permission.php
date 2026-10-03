<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('item_masters', fn (Blueprint $table) => $table->softDeletes());

        $permission = Permission::findOrCreate('items.delete-master', 'web');
        Role::query()->where('name', 'Administrator')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (DB::table('items')->whereNotNull('deleted_at')->exists()
            || DB::table('item_masters')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Rollback akan membuka kembali data barang yang diarsipkan. Pulihkan cadangan database jika perlu.');
        }
        Schema::table('items', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('item_masters', fn (Blueprint $table) => $table->dropSoftDeletes());
        // Role assignments may have been changed manually after migration.
    }
};
