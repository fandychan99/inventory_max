<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use SoftDeletes;

    protected $fillable = ['location_id', 'master_item_id', 'sku', 'name', 'unit', 'quantity', 'minimum_stock', 'description', 'is_active'];

    protected static function booted(): void
    {
        static::creating(function (Item $item): void {
            $master = $item->master_item_id
                ? ItemMaster::findOrFail($item->master_item_id)
                : ItemMaster::firstOrCreate(['sku' => $item->sku], [
                    'name' => $item->name,
                    'unit' => $item->unit ?: 'unit',
                    'description' => $item->description,
                ]);

            $item->master_item_id = $master->id;
            $item->sku = $master->sku;
            $item->name = $master->name;
            $item->unit = $master->unit;
            $item->description = $master->description;
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(ItemMaster::class, 'master_item_id')->withTrashed();
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

    public function inspectionEntries(): HasMany
    {
        return $this->hasMany(InspectionEntry::class);
    }

    public function getAvailableAttribute(): int
    {
        if ($this->location->workflow !== Location::WORKFLOW_LOAN) {
            return $this->quantity;
        }

        $issued = $this->loans()->where('status', 'issued')->sum('quantity');

        return max(0, $this->quantity - $issued);
    }
}
