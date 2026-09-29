<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Location;
use App\Services\InspectionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public function index(): View
    {
        return view('inspections.index', [
            'locations' => Location::with(['type', 'latestInspection.inspector'])
                ->withCount(['items as active_items_count' => fn ($query) => $query->where('is_active', true)])
                ->visible()->where('workflow', Location::WORKFLOW_CHECKLIST)->orderBy('name')->get(),
            'recentInspections' => Inspection::with(['location', 'inspector'])->latest()->take(15)->get(),
        ]);
    }

    public function create(Location $location): View
    {
        abort_unless($location->workflow === Location::WORKFLOW_CHECKLIST && ! $location->archived_at, 404);

        return view('inspections.create', [
            'location' => $location->load('type'),
            'items' => $location->items()->where('is_active', true)->orderBy('sku')->get(),
            'latestInspection' => $location->inspections()->with('inspector')->latest()->first(),
            'history' => $location->inspections()->with('inspector')->latest()->paginate(10),
        ]);
    }

    public function store(Request $request, Location $location, InspectionWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*' => ['required', 'array'],
            'rows.*.actual_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'rows.*.condition' => ['required', Rule::in(['good', 'damaged'])],
            'rows.*.note' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $inspection = $workflow->record($location, $request->user(), $data['rows'], $data['note'] ?? null);

        return redirect()->route('inspections.show', $inspection)->with('success', 'Pengecekan peralatan berhasil dicatat.');
    }

    public function show(Inspection $inspection): View
    {
        abort_unless($inspection->location->workflow === Location::WORKFLOW_CHECKLIST, 404);

        return view('inspections.show', [
            'inspection' => $inspection->load(['location.type', 'inspector', 'entries']),
        ]);
    }

    public function label(Location $location): View
    {
        abort_unless($location->workflow === Location::WORKFLOW_CHECKLIST && ! $location->archived_at && filled($location->scan_code), 404);

        return view('inspections.label', compact('location'));
    }
}
