<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    protected $fillable = ['location_id', 'inspected_by', 'inspected_on', 'status', 'note'];

    protected function casts(): array
    {
        return ['inspected_on' => 'date'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(InspectionEntry::class);
    }
}
