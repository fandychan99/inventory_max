<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('scan_code', 80)->nullable()->unique();
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspected_by')->constrained('users')->restrictOnDelete();
            $table->date('inspected_on');
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['location_id', 'inspected_on']);
        });

        Schema::create('inspection_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('item_sku', 80);
            $table->string('item_name');
            $table->string('unit', 30);
            $table->unsignedInteger('expected_quantity');
            $table->unsignedInteger('actual_quantity');
            $table->string('condition', 20);
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['inspection_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_entries');
        Schema::dropIfExists('inspections');
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['scan_code']);
            $table->dropColumn('scan_code');
        });
    }
};
