<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Location extends Model
{
    public const WORKFLOW_LOAN = 'loan';

    public const WORKFLOW_STOCK = 'stock';

    protected $fillable = ['location_type_id', 'name', 'workflow', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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

    public function getWorkflowLabelAttribute(): string
    {
        return $this->workflow === self::WORKFLOW_LOAN ? 'Pinjam kembali' : 'Permintaan stok';
    }
}
