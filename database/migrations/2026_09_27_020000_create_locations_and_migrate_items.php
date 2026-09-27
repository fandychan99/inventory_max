<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_type_id')->constrained()->restrictOnDelete();
            $table->string('name', 120)->unique();
            $table->string('workflow', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['workflow', 'is_active']);
        });

        $now = now();
        $typeId = DB::table('location_types')->insertGetId(['name' => 'Gudang', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('location_types')->insert([
            ['name' => 'Truk', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lemari', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $loanLocationId = DB::table('locations')->insertGetId([
            'location_type_id' => $typeId, 'name' => 'Gudang A', 'workflow' => 'loan',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $stockLocationId = DB::table('locations')->insertGetId([
            'location_type_id' => $typeId, 'name' => 'Gudang B', 'workflow' => 'stock',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('warehouse')->constrained()->restrictOnDelete();
        });
        DB::table('items')->where('warehouse', 'A')->update(['location_id' => $loanLocationId]);
        DB::table('items')->where('warehouse', 'B')->update(['location_id' => $stockLocationId]);

        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['warehouse', 'is_active']);
            $table->dropColumn('warehouse');
            $table->index(['location_id', 'is_active']);
        });
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('locations')->count() !== 2
            || DB::table('locations')->where('name', 'Gudang A')->where('workflow', 'loan')->count() !== 1
            || DB::table('locations')->where('name', 'Gudang B')->where('workflow', 'stock')->count() !== 1
            || DB::table('location_types')->count() !== 3
            || DB::table('location_types')->whereIn('name', ['Gudang', 'Truk', 'Lemari'])->count() !== 3) {
            throw new RuntimeException('Rollback akan menghapus data lokasi baru. Pulihkan cadangan database jika perlu kembali ke skema lama.');
        }

        Schema::table('items', function (Blueprint $table) {
            $table->enum('warehouse', ['A', 'B'])->nullable();
        });
        DB::table('items')->whereIn('location_id', DB::table('locations')->select('id')->where('workflow', 'loan'))
            ->update(['warehouse' => 'A']);
        DB::table('items')->whereIn('location_id', DB::table('locations')->select('id')->where('workflow', 'stock'))
            ->update(['warehouse' => 'B']);
        Schema::table('items', function (Blueprint $table) {
            $table->enum('warehouse', ['A', 'B'])->nullable(false)->change();
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'is_active']);
            $table->dropConstrainedForeignId('location_id');
            $table->index(['warehouse', 'is_active']);
        });
        Schema::dropIfExists('locations');
        Schema::dropIfExists('location_types');
    }
};
