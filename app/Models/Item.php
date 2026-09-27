<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = ['location_id', 'sku', 'name', 'unit', 'quantity', 'minimum_stock', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function stockRequests(): HasMany
    {
        return $this->hasMany(StockRequest::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getAvailableAttribute(): int
    {
        if ($this->location->workflow === Location::WORKFLOW_STOCK) {
            return $this->quantity;
        }

        $issued = $this->loans()->where('status', 'issued')->sum('quantity');

        return max(0, $this->quantity - $issued);
    }
}
