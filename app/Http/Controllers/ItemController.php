<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Services\InventoryWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $location = $request->filled('location')
            ? Location::visible()->findOrFail($request->integer('location'))
            : Location::visible()->orderBy('id')->firstOrFail();
        $items = Item::where('location_id', $location->id)->when($request->query('q'), function ($query, $q) {
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"));
        })->orderBy('name')->paginate(15)->withQueryString();

        return view('items.index', compact('items', 'location'));
    }

    public function create(Request $request): View
    {
        $location = $request->filled('location')
            ? Location::visible()->findOrFail($request->integer('location'))
            : Location::visible()->where('is_active', true)->orderBy('id')->firstOrFail();
        abort_unless($location->is_active, 422);

        return view('items.form', ['item' => new Item(['location_id' => $location->id]), 'location' => $location]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $location = Location::visible()->findOrFail($data['location_id']);
        abort_unless($location->is_active, 422);
        $data['quantity'] = $location->workflow === Location::WORKFLOW_STOCK ? 0 : $data['quantity'];
        $data['is_active'] = true;
        $item = Item::create($data);

        return redirect()->route('items.index', ['location' => $item->location_id])->with('success', 'Barang berhasil ditambahkan.');
    }

    public function edit(Item $item): View
    {
        abort_if($item->location->archived_at, 404);

        return view('items.form', ['item' => $item, 'location' => $item->location]);
    }

    public function update(Request $request, Item $item, InventoryWorkflow $workflow): RedirectResponse
    {
        abort_if($item->location->archived_at, 404);
        $data = $this->validated($request, $item);
        $stockNote = $request->validate(['stock_change_note' => ['nullable', 'string', 'max:255']])['stock_change_note'] ?? null;
        DB::transaction(function () use ($request, $item, $data, $stockNote, $workflow) {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            if ($item->location_id !== (int) $data['location_id']) {
                throw ValidationException::withMessages(['location_id' => 'Lokasi barang tidak dapat diubah dari form ini.']);
            }
            if ($item->location->workflow === Location::WORKFLOW_LOAN && $data['quantity'] < $item->loans()->where('status', 'issued')->sum('quantity')) {
                throw ValidationException::withMessages(['quantity' => 'Total alat lebih kecil dari jumlah yang sedang dipinjam.']);
            }
            if ($item->location->workflow === Location::WORKFLOW_STOCK) {
                $change = $data['quantity'] - $item->quantity;
                if ($change !== 0) {
                    abort_unless($request->user()->can('stock.adjust'), 403);
                    if (blank($stockNote)) {
                        throw ValidationException::withMessages(['stock_change_note' => 'Alasan perubahan stok wajib diisi.']);
                    }
                    $workflow->adjustStock($item, $change, $stockNote, $request->user());
                }
                unset($data['quantity']);
            }
            $data['is_active'] = $request->boolean('is_active');
            $item->update($data);
        });

        return redirect()->route('items.index', ['location' => $item->location_id])->with('success', 'Data barang berhasil diperbarui.');
    }

    public function movements(Item $item): View
    {
        abort_unless($item->location->workflow === Location::WORKFLOW_STOCK, 404);

        return view('items.movements', ['item' => $item, 'movements' => $item->movements()->with('actor')->latest()->paginate(20)]);
    }

    public function label(Item $item): View
    {
        return view('items.label', compact('item'));
    }

    public function adjust(Request $request, Item $item, InventoryWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['change' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'], 'note' => ['required', 'string', 'max:255']]);
        $workflow->adjustStock($item, (int) $data['change'], $data['note'], $request->user());

        return back()->with('success', 'Stok dan riwayat mutasi berhasil diperbarui.');
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'sku' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._\/-]+$/', Rule::unique('items', 'sku')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'minimum_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if (Location::where('scan_code', $data['sku'])->exists()) {
            throw ValidationException::withMessages(['sku' => 'Kode barang sudah digunakan sebagai barcode lokasi.']);
        }

        return $data;
    }
}
