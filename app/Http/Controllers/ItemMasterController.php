<?php

namespace App\Http\Controllers;

use App\Models\ItemMaster;
use App\Models\Location;
use App\Services\CatalogRemoval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ItemMasterController extends Controller
{
    public function index(Request $request): View
    {
        $archived = $request->boolean('archived') && $request->user()->can('items.delete-master');
        $masters = ItemMaster::query()->when($archived, fn ($query) => $query->onlyTrashed())
            ->when($request->query('q'), fn ($query, $search) => $query->where(fn ($match) => $match
                ->where('sku', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->withCount('items')->orderBy('name')->paginate(20)->withQueryString();

        return view('item-masters.index', compact('masters', 'archived'));
    }

    public function create(): View
    {
        return view('item-masters.form', ['master' => new ItemMaster]);
    }

    public function store(Request $request): RedirectResponse
    {
        $master = ItemMaster::create($this->validated($request));

        return redirect()->route('item-masters.index')->with('success', "Master barang {$master->name} berhasil dibuat. Sekarang barang dapat ditempatkan ke lokasi.");
    }

    public function edit(ItemMaster $itemMaster): View
    {
        return view('item-masters.form', ['master' => $itemMaster]);
    }

    public function update(Request $request, ItemMaster $itemMaster): RedirectResponse
    {
        $data = $this->validated($request, $itemMaster);
        DB::transaction(function () use ($itemMaster, $data): void {
            $itemMaster->update($data);
            $itemMaster->items()->update($data);
        });

        return redirect()->route('item-masters.index')->with('success', 'Master barang dan seluruh penempatannya berhasil diperbarui.');
    }

    public function destroy(ItemMaster $itemMaster, CatalogRemoval $removal): RedirectResponse
    {
        $archived = $removal->removeMaster($itemMaster);

        return redirect()->route('item-masters.index')->with('success', $archived
            ? 'Master dan seluruh penempatannya diarsipkan. Riwayat transaksi tetap tersimpan.'
            : 'Master barang dan seluruh penempatannya berhasil dihapus.');
    }

    public function restore(int $itemMaster): RedirectResponse
    {
        $master = ItemMaster::onlyTrashed()->findOrFail($itemMaster);
        $master->restore();

        return redirect()->route('item-masters.index', ['archived' => 1])
            ->with('success', 'Master barang dipulihkan. Penempatan yang diarsipkan dapat dipulihkan di katalog lokasi.');
    }

    private function validated(Request $request, ?ItemMaster $master = null): array
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9._\/-]+$/', Rule::unique('item_masters', 'sku')->ignore($master?->id)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if (Location::where('scan_code', $data['sku'])->exists()) {
            throw ValidationException::withMessages(['sku' => 'Kode barang sudah digunakan sebagai barcode lokasi.']);
        }

        return $data;
    }
}
