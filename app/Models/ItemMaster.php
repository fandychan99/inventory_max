<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemMaster extends Model
{
    use SoftDeletes;

    protected $fillable = ['sku', 'name', 'unit', 'description'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'master_item_id');
    }
}
