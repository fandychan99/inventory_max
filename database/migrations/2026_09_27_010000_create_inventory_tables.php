<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->enum('warehouse', ['A', 'B']);
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('unit', 30)->default('unit');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['warehouse', 'is_active']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->date('needed_from');
            $table->date('due_on');
            $table->text('purpose');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_note')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_on']);
        });

        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('purpose');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('stock_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->integer('change');
            $table->unsignedInteger('balance_after');
            $table->string('type', 20);
            $table->string('note');
            $table->timestamps();
            $table->index(['item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_requests');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('items');
    }
};
