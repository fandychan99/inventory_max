<?php

namespace App\Http\Controllers;

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
            'types' => LocationType::withCount('locations')->orderBy('name')->get(),
            'locations' => Location::with('type')->withCount('items')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Location::create([...$data, 'is_active' => true]);

        return back()->with('success', 'Lokasi berhasil ditambahkan.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $data = $this->validated($request, $location);
        if ($data['workflow'] !== $location->workflow && $location->items()->exists()) {
            throw ValidationException::withMessages(['workflow' => 'Alur lokasi tidak dapat diubah karena sudah memiliki barang.']);
        }
        $data['is_active'] = $request->boolean('is_active');
        $location->update($data);

        return back()->with('success', 'Lokasi berhasil diperbarui.');
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'location_type_id' => ['required', 'integer', Rule::exists('location_types', 'id')],
            'name' => ['required', 'string', 'max:120', Rule::unique('locations', 'name')->ignore($location?->id)],
            'workflow' => ['required', Rule::in([Location::WORKFLOW_LOAN, Location::WORKFLOW_STOCK])],
        ]);
    }
}
