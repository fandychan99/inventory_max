<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Services\InventoryWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(Request $request): View
    {
        $loans = Loan::with(['item.location', 'requester'])->when(! $request->user()->can('loans.view-all'), fn ($q) => $q->where('requester_id', $request->user()->id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('location'), fn ($q, $id) => $q->whereHas('item', fn ($items) => $items->where('location_id', $id)))
            ->latest()->paginate(15)->withQueryString();

        return view('loans.index', ['loans' => $loans, 'locations' => Location::visible()->where('workflow', Location::WORKFLOW_LOAN)->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('loans.create', ['items' => Item::with('location')->where('is_active', true)
            ->whereHas('location', fn ($q) => $q->visible()->where('workflow', Location::WORKFLOW_LOAN)->where('is_active', true))
            ->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'needed_from' => ['required', 'date', 'after_or_equal:today'],
            'due_on' => ['required', 'date', 'after_or_equal:needed_from'],
            'purpose' => ['required', 'string', 'max:2000'],
        ]);
        $item = Item::findOrFail($data['item_id']);
        abort_unless($item->location->workflow === Location::WORKFLOW_LOAN && $item->location->is_active && $item->is_active, 422);
        $data['requester_id'] = $request->user()->id;
        $loan = Loan::create($data);

        return redirect()->route('loans.show', $loan)->with('success', 'Pengajuan peminjaman berhasil dikirim.');
    }

    public function show(Request $request, Loan $loan): View
    {
        abort_unless($request->user()->can('loans.view-all') || $loan->requester_id === $request->user()->id, 403);

        return view('loans.show', ['loan' => $loan->load(['item.location', 'requester', 'approver', 'issuer', 'receiver'])]);
    }

    public function transition(Request $request, Loan $loan, string $action, InventoryWorkflow $workflow): RedirectResponse
    {
        abort_unless(in_array($action, ['approve', 'reject', 'issue', 'return'], true), 404);
        $permission = match ($action) {
            'approve', 'reject' => 'loans.approve', 'issue', 'return' => 'loans.handover'
        };
        abort_unless($request->user()->can($permission), 403);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $workflow->transitionLoan($loan, $action, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Status peminjaman berhasil diperbarui.');
    }
}
