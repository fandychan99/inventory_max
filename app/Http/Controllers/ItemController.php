<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemMaster;
use App\Models\Location;
use App\Services\InventoryWorkflow;
use App\Services\CatalogRemoval;
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
        $archived = $request->boolean('archived') && $request->user()->can('items.manage');
        $items = Item::query()->when($archived, fn ($query) => $query->onlyTrashed())
            ->where('location_id', $location->id)->when($request->query('q'), function ($query, $q) {
            $query->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"));
        })->orderBy('name')->paginate(15)->withQueryString();

        return view('items.index', compact('items', 'location', 'archived'));
    }

    public function create(Request $request): View
    {
        $location = $request->filled('location')
            ? Location::visible()->findOrFail($request->integer('location'))
            : Location::visible()->where('is_active', true)->orderBy('id')->firstOrFail();
        abort_unless($location->is_active, 422);

        return view('items.form', [
            'item' => new Item(['location_id' => $location->id]),
            'location' => $location,
            'masters' => ItemMaster::whereDoesntHave('items', fn ($query) => $query->withTrashed()->where('location_id', $location->id))
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, InventoryWorkflow $workflow): RedirectResponse
    {
        $data = $this->validated($request);
        $location = Location::visible()->findOrFail($data['location_id']);
        abort_unless($location->is_active, 422);
        $stockNote = $request->validate(['stock_change_note' => ['nullable', 'string', 'max:255']])['stock_change_note'] ?? null;
        $initialStock = $location->workflow === Location::WORKFLOW_STOCK ? (int) $data['quantity'] : 0;
        if ($initialStock > 0) {
            abort_unless($request->user()->can('stock.adjust'), 403);
            $data['quantity'] = 0;
        }

        $item = DB::transaction(function () use ($data, $initialStock, $stockNote, $request, $workflow) {
            $item = Item::create([...$data, 'is_active' => true]);
            if ($initialStock > 0) {
                $workflow->adjustStock($item, $initialStock, filled($stockNote) ? $stockNote : 'Stok awal', $request->user());
            }

            return $item;
        });

        return redirect()->route('items.index', ['location' => $item->location_id])->with('success', 'Barang berhasil ditambahkan.');
    }

    public function edit(Item $item): View
    {
        abort_if($item->location->archived_at, 404);

        return view('items.form', ['item' => $item->load('master'), 'location' => $item->location]);
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

    public function destroy(Item $item, CatalogRemoval $removal): RedirectResponse
    {
        abort_if($item->location->archived_at, 404);
        $locationId = $item->location_id;
        $archived = $removal->removePlacement($item);

        return redirect()->route('items.index', ['location' => $locationId])
            ->with('success', $archived
                ? 'Barang diarsipkan dari lokasi ini. Riwayat transaksinya tetap tersimpan.'
                : 'Barang dihapus dari lokasi ini. Master barang tetap tersedia.');
    }

    public function restore(int $item): RedirectResponse
    {
        $placement = DB::transaction(function () use ($item): Item {
            $placement = Item::onlyTrashed()->with('location', 'master')->lockForUpdate()->findOrFail($item);
            abort_if($placement->location->archived_at || $placement->master->trashed(), 422);
            $placement->restore();
            $placement->update($placement->master->only(['sku', 'name', 'unit', 'description']));

            return $placement;
        });

        return redirect()->route('items.index', ['location' => $placement->location_id, 'archived' => 1])
            ->with('success', 'Penempatan barang berhasil dipulihkan.');
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        $rules = [
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'minimum_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
        if ($item === null) {
            $rules['master_item_id'] = [
                'required', 'integer', Rule::exists('item_masters', 'id')->whereNull('deleted_at'),
                Rule::unique('items', 'master_item_id')->where('location_id', $request->input('location_id')),
            ];
        }

        return $request->validate($rules);
    }
}
