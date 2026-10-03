<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemMaster;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogRemoval
{
    public function removePlacement(Item $placement): bool
    {
        return DB::transaction(function () use ($placement): bool {
            $placement = Item::query()->lockForUpdate()->findOrFail($placement->id);
            $this->assertRemovable($placement);

            return $this->remove($placement);
        });
    }

    public function removeMaster(ItemMaster $master): bool
    {
        return DB::transaction(function () use ($master): bool {
            $master = ItemMaster::query()->lockForUpdate()->findOrFail($master->id);
            $placements = $master->items()->withTrashed()->lockForUpdate()->get();
            foreach ($placements as $placement) {
                if (! $placement->trashed()) {
                    $this->assertRemovable($placement);
                }
            }
            foreach ($placements as $placement) {
                if ($placement->trashed() && $this->hasHistory($placement)) {
                    continue;
                }
                $this->remove($placement);
            }

            if ($master->items()->withTrashed()->exists()) {
                $master->delete();

                return true;
            }

            $master->forceDelete();

            return false;
        });
    }

    private function assertRemovable(Item $placement): void
    {
        $workflow = $placement->location->workflow;
        if ($workflow !== Location::WORKFLOW_CHECKLIST && $placement->quantity > 0) {
            throw ValidationException::withMessages(['remove' => 'Jumlah barang di lokasi ini harus 0 sebelum dihapus.']);
        }
        if ($placement->loans()->whereIn('status', ['submitted', 'approved', 'issued'])->exists()
            || $placement->stockRequests()->whereIn('status', ['submitted', 'approved'])->exists()) {
            throw ValidationException::withMessages(['remove' => 'Masih ada peminjaman atau permintaan yang berjalan untuk barang ini.']);
        }
    }

    private function remove(Item $placement): bool
    {
        if ($this->hasHistory($placement)) {
            if (! $placement->trashed()) {
                $placement->delete();
            }

            return true;
        }

        $placement->forceDelete();

        return false;
    }

    private function hasHistory(Item $placement): bool
    {
        return $placement->loans()->exists()
            || $placement->stockRequests()->exists()
            || $placement->movements()->exists()
            || $placement->inspectionEntries()->exists();
    }
}
