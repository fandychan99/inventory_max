<?php

namespace App\Http\Controllers;

use App\Models\LocationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('location_types', 'name')->ignore($locationType->id)],
        ]);
        $locationType->update($data);

        return back()->with('success', 'Jenis lokasi berhasil diperbarui.');
    }
}
