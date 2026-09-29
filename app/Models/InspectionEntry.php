<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionEntry extends Model
{
    protected $fillable = [
        'inspection_id', 'item_id', 'item_sku', 'item_name', 'unit',
        'expected_quantity', 'actual_quantity', 'condition', 'note',
    ];

    protected function casts(): array
    {
        return ['expected_quantity' => 'integer', 'actual_quantity' => 'integer'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getHasIssueAttribute(): bool
    {
        return $this->actual_quantity !== $this->expected_quantity || $this->condition !== 'good';
    }
}
