<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Models\LocationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(): View
    {
        return view('locations.index', [
            'types' => LocationType::visible()->withCount(['locations as visible_locations_count' => fn ($query) => $query->visible()])->orderBy('name')->get(),
            'archivedTypes' => LocationType::whereNotNull('archived_at')->withCount('locations')->orderBy('name')->get(),
            'locations' => Location::visible()->with('type')->withCount('items')->orderBy('name')->get(),
            'archivedLocations' => Location::whereNotNull('archived_at')->with('type')->withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['scan_code'] = $data['workflow'] === Location::WORKFLOW_CHECKLIST ? $data['scan_code'] : null;
        Location::create([...$data, 'is_active' => true]);

        return back()->with('success', 'Lokasi berhasil ditambahkan.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        abort_if($location->archived_at, 404);
        $data = $this->validated($request, $location);
        if ($data['workflow'] !== $location->workflow && ($location->items()->exists() || $location->inspections()->exists())) {
            throw ValidationException::withMessages(['workflow' => 'Alur lokasi tidak dapat diubah karena sudah memiliki barang atau riwayat pengecekan.']);
        }
        $data['scan_code'] = $data['workflow'] === Location::WORKFLOW_CHECKLIST ? $data['scan_code'] : null;
        $data['is_active'] = $request->boolean('is_active');
        $location->update($data);

        return back()->with('success', 'Lokasi berhasil diperbarui.');
    }

    public function archive(Location $location): RedirectResponse
    {
        abort_if($location->archived_at, 404);
        $location->update(['archived_at' => now(), 'is_active' => false]);

        return back()->with('success', 'Lokasi diarsipkan dan dihilangkan dari katalog. Riwayat tetap tersimpan.');
    }

    public function restore(Location $location): RedirectResponse
    {
        abort_unless($location->archived_at, 404);
        if ($location->type->archived_at) {
            throw ValidationException::withMessages(['location_type_id' => 'Pulihkan jenis lokasi terlebih dahulu.']);
        }
        $location->update(['archived_at' => null]);

        return back()->with('success', 'Lokasi dipulihkan. Aktifkan lokasi saat siap digunakan lagi.');
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        $data = $request->validate([
            'location_type_id' => ['required', 'integer', Rule::exists('location_types', 'id')->whereNull('archived_at')],
            'name' => ['required', 'string', 'max:120', Rule::unique('locations', 'name')->ignore($location?->id)],
            'workflow' => ['required', Rule::in([Location::WORKFLOW_LOAN, Location::WORKFLOW_STOCK, Location::WORKFLOW_CHECKLIST])],
            'scan_code' => ['nullable', Rule::requiredIf($request->input('workflow') === Location::WORKFLOW_CHECKLIST), 'string', 'max:80', 'regex:/^[A-Za-z0-9._\/-]+$/', Rule::unique('locations', 'scan_code')->ignore($location?->id)],
        ]);

        if ($data['workflow'] === Location::WORKFLOW_CHECKLIST && Item::where('sku', $data['scan_code'])->exists()) {
            throw ValidationException::withMessages(['scan_code' => 'Kode lokasi sudah digunakan sebagai kode barang.']);
        }

        return $data;
    }
}
