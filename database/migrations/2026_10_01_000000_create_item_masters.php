<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_masters', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 80)->unique();
            $table->string('name');
            $table->string('unit', 30)->default('unit');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('master_item_id')->nullable()->constrained('item_masters')->restrictOnDelete();
        });

        DB::table('items')->orderBy('id')->chunkById(100, function ($items): void {
            foreach ($items as $item) {
                $masterId = DB::table('item_masters')->insertGetId([
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'description' => $item->description,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
                DB::table('items')->where('id', $item->id)->update(['master_item_id' => $masterId]);
            }
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('master_item_id')->nullable(false)->change();
            $table->dropUnique('items_sku_unique');
            $table->unique(['location_id', 'master_item_id']);
            $table->unique(['location_id', 'sku']);
        });
    }

    public function down(): void
    {
        if (DB::table('items')->select('sku')->groupBy('sku')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback akan menghapus dukungan barang yang sama di beberapa lokasi. Pulihkan cadangan database jika perlu.');
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['location_id', 'master_item_id']);
            $table->dropUnique(['location_id', 'sku']);
            $table->unique('sku');
            $table->dropConstrainedForeignId('master_item_id');
        });
        Schema::dropIfExists('item_masters');
    }
};
