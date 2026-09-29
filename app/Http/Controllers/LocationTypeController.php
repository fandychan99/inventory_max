<?php

namespace App\Http\Controllers;

use App\Models\LocationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocationTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('location_types', 'name')],
        ]);
        LocationType::create($data);

        return back()->with('success', 'Jenis lokasi berhasil ditambahkan.');
    }

    public function update(Request $request, LocationType $locationType): RedirectResponse
    {
        abort_if($locationType->archived_at, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('location_types', 'name')->ignore($locationType->id)],
        ]);
        $locationType->update($data);

        return back()->with('success', 'Jenis lokasi berhasil diperbarui.');
    }

    public function archive(LocationType $locationType): RedirectResponse
    {
        abort_if($locationType->archived_at, 404);
        if ($locationType->locations()->visible()->exists()) {
            throw ValidationException::withMessages(['location_type_id' => 'Arsipkan semua lokasi dalam jenis ini terlebih dahulu.']);
        }
        $locationType->update(['archived_at' => now()]);

        return back()->with('success', 'Jenis lokasi diarsipkan dan dihilangkan dari pilihan.');
    }

    public function restore(LocationType $locationType): RedirectResponse
    {
        abort_unless($locationType->archived_at, 404);
        $locationType->update(['archived_at' => null]);

        return back()->with('success', 'Jenis lokasi dipulihkan.');
    }
}
