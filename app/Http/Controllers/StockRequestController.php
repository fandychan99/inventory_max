<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Models\StockRequest;
use App\Services\InventoryWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = StockRequest::with(['item.location', 'requester'])->when(! $request->user()->can('requests.view-all'), fn ($q) => $q->where('requester_id', $request->user()->id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('location'), fn ($q, $id) => $q->whereHas('item', fn ($items) => $items->where('location_id', $id)))
            ->latest()->paginate(15)->withQueryString();

        return view('requests.index', ['requests' => $requests, 'locations' => Location::visible()->where('workflow', Location::WORKFLOW_STOCK)->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('requests.create', ['items' => Item::with('location')->where('is_active', true)
            ->whereHas('location', fn ($q) => $q->visible()->where('workflow', Location::WORKFLOW_STOCK)->where('is_active', true))
            ->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'purpose' => ['required', 'string', 'max:2000'],
        ]);
        $item = Item::findOrFail($data['item_id']);
        abort_unless($item->location->workflow === Location::WORKFLOW_STOCK && $item->location->is_active && $item->is_active, 422);
        $data['requester_id'] = $request->user()->id;
        $stockRequest = StockRequest::create($data);

        return redirect()->route('requests.show', $stockRequest)->with('success', 'Permintaan barang berhasil dikirim.');
    }

    public function show(Request $request, StockRequest $stockRequest): View
    {
        abort_unless($request->user()->can('requests.view-all') || $stockRequest->requester_id === $request->user()->id, 403);

        return view('requests.show', ['stockRequest' => $stockRequest->load(['item.location', 'requester', 'approver', 'fulfiller'])]);
    }

    public function transition(Request $request, StockRequest $stockRequest, string $action, InventoryWorkflow $workflow): RedirectResponse
    {
        abort_unless(in_array($action, ['approve', 'reject', 'fulfill'], true), 404);
        $permission = $action === 'fulfill' ? 'requests.fulfill' : 'requests.approve';
        abort_unless($request->user()->can($permission), 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $workflow->transitionRequest($stockRequest, $action, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Status permintaan berhasil diperbarui.');
    }
}
