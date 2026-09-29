<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Location extends Model
{
    public const WORKFLOW_LOAN = 'loan';

    public const WORKFLOW_STOCK = 'stock';

    public const WORKFLOW_CHECKLIST = 'checklist';

    protected $fillable = ['location_type_id', 'name', 'workflow', 'scan_code', 'is_active', 'archived_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'archived_at' => 'datetime'];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LocationType::class, 'location_type_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function loans(): HasManyThrough
    {
        return $this->hasManyThrough(Loan::class, Item::class);
    }

    public function stockRequests(): HasManyThrough
    {
        return $this->hasManyThrough(StockRequest::class, Item::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function latestInspection(): HasOne
    {
        return $this->hasOne(Inspection::class)->latestOfMany();
    }

    public function getWorkflowLabelAttribute(): string
    {
        return match ($this->workflow) {
            self::WORKFLOW_LOAN => 'Pinjam kembali',
            self::WORKFLOW_STOCK => 'Permintaan stok',
            self::WORKFLOW_CHECKLIST => 'Pengecekan rutin',
        };
    }
}
