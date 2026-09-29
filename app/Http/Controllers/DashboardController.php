<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\StockRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $loans = Loan::query()->when(! $request->user()->can('loans.view-all'), fn ($query) => $query->where('requester_id', $request->user()->id));
        $requests = StockRequest::query()->when(! $request->user()->can('requests.view-all'), fn ($query) => $query->where('requester_id', $request->user()->id));
        $locations = Location::with(['type', 'latestInspection'])->withCount([
            'items as active_items_count' => fn ($query) => $query->where('is_active', true),
            'loans as pending_loans_count' => fn ($query) => $query->where('status', 'submitted')
                ->when(! $request->user()->can('loans.view-all'), fn ($q) => $q->where('requester_id', $request->user()->id)),
            'stockRequests as pending_requests_count' => fn ($query) => $query->where('status', 'submitted')
                ->when(! $request->user()->can('requests.view-all'), fn ($q) => $q->where('requester_id', $request->user()->id)),
        ])->visible()->orderByDesc('is_active')->orderBy('name')->get();

        return view('dashboard', [
            'locations' => $locations,
            'activeLoans' => (clone $loans)->where('status', 'issued')->count(),
            'overdueLoans' => (clone $loans)->where('status', 'issued')->whereDate('due_on', '<', today())->count(),
            'pendingLoans' => (clone $loans)->where('status', 'submitted')->count(),
            'lowStock' => Item::whereHas('location', fn ($query) => $query->visible()->where('workflow', Location::WORKFLOW_STOCK))
                ->where('is_active', true)->whereColumn('quantity', '<=', 'minimum_stock')->count(),
            'pendingRequests' => (clone $requests)->where('status', 'submitted')->count(),
            'checklistLocations' => Location::visible()->where('workflow', Location::WORKFLOW_CHECKLIST)->where('is_active', true)->count(),
            'checkedToday' => Inspection::whereDate('inspected_on', today())->whereHas('location', fn ($query) => $query->visible()->where('is_active', true))->distinct('location_id')->count('location_id'),
            'recentLoans' => (clone $loans)->with(['item.location', 'requester'])->latest()->take(5)->get(),
            'recentRequests' => (clone $requests)->with(['item.location', 'requester'])->latest()->take(5)->get(),
        ]);
    }
}
