<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemMaster extends Model
{
    protected $fillable = ['sku', 'name', 'unit', 'description'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'master_item_id');
    }
}
