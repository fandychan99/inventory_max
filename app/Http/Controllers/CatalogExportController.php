<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Services\CatalogWorkbook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CatalogExportController extends Controller
{
    public function __invoke(Request $request, CatalogWorkbook $workbook): BinaryFileResponse
    {
        $filters = $request->validate([
            'location' => ['required', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $location = Location::visible()->findOrFail($filters['location']);

        $query = Item::query()->with('location.type')->where('location_id', $location->id)
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"));
            });
        if ($location->workflow === Location::WORKFLOW_LOAN) {
            $query->withSum(['loans as issued_quantity' => fn ($loans) => $loans->where('status', 'issued')], 'quantity');
        }

        $path = $workbook->create($query->lazyById(500), $location);
        $name = 'katalog-lokasi-'.$location->id.'-'.now()->format('Ymd-His').'.xlsx';

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
