<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\StockRequest;
use App\Services\InventoryWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['code' => ['nullable', 'string', 'max:80']]);
        $code = trim($data['code'] ?? '');
        $item = $code !== '' ? Item::with('location')->where('sku', $code)->first() : null;

        $loans = collect();
        $stockRequests = collect();
        if ($item?->location->workflow === Location::WORKFLOW_LOAN && $request->user()->can('loans.handover')) {
            $loans = Loan::with('requester')->where('item_id', $item->id)
                ->whereIn('status', ['approved', 'issued'])->oldest()->get();
        }
        if ($item?->location->workflow === Location::WORKFLOW_STOCK && $request->user()->can('requests.fulfill')) {
            $stockRequests = StockRequest::with('requester')->where('item_id', $item->id)
                ->where('status', 'approved')->oldest()->get();
        }

        return view('scan.index', compact('code', 'item', 'loans', 'stockRequests'));
    }

    public function moveStock(Request $request, InventoryWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('stock.adjust'), 403);
        $data = $request->validate([
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['required', 'string', 'max:255'],
        ]);
        $item = Item::findOrFail($data['item_id']);
        abort_unless($item->location->workflow === Location::WORKFLOW_STOCK && $item->is_active, 422);

        $change = $data['direction'] === 'in' ? (int) $data['quantity'] : -(int) $data['quantity'];
        $workflow->adjustStock($item, $change, $data['note'], $request->user(), $data['direction']);

        return redirect()->route('scan.index', ['code' => $item->sku])->with('success', 'Mutasi stok dari hasil scan berhasil dicatat.');
    }
}
