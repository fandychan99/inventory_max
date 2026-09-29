<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Services\StockWorkbook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockExportController extends Controller
{
    public function __invoke(Request $request, StockWorkbook $workbook): BinaryFileResponse
    {
        $filters = $request->validate([
            'location' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $location = isset($filters['location']) ? Location::visible()->findOrFail($filters['location']) : null;
        abort_if($location && $location->workflow !== Location::WORKFLOW_STOCK, 404);

        $items = Item::query()->with('location.type')
            ->whereHas('location', fn ($query) => $query->visible()->where('workflow', Location::WORKFLOW_STOCK))
            ->when($location, fn ($query) => $query->where('location_id', $location->id))
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"));
            })->lazyById(500);

        $path = $workbook->create($items);
        $name = 'stok-'.($location ? 'lokasi-'.$location->id.'-' : 'semua-').now()->format('Ymd-His').'.xlsx';

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
