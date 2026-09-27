<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryWorkflow
{
    public function transitionLoan(Loan $loan, string $action, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($loan, $action, $actor, $note) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $item = Item::query()->lockForUpdate()->findOrFail($loan->item_id);
            abort_unless($item->location->workflow === Location::WORKFLOW_LOAN, 422);

            $next = match ([$loan->status, $action]) {
                ['submitted', 'approve'] => 'approved',
                ['submitted', 'reject'] => 'rejected',
                ['approved', 'issue'] => 'issued',
                ['issued', 'return'] => 'returned',
                default => throw ValidationException::withMessages(['status' => 'Aksi tidak berlaku untuk status peminjaman ini.']),
            };

            if ($action === 'issue' && $loan->quantity > $item->available) {
                throw ValidationException::withMessages(['quantity' => 'Jumlah alat tersedia tidak mencukupi.']);
            }
            if ($action === 'reject' && blank($note)) {
                throw ValidationException::withMessages(['note' => 'Alasan penolakan wajib diisi.']);
            }

            $loan->status = $next;
            if ($action === 'approve') {
                $loan->approved_by = $actor->id;
                $loan->approved_at = now();
            }
            if ($action === 'issue') {
                $loan->issued_by = $actor->id;
                $loan->issued_at = now();
            }
            if ($action === 'return') {
                $loan->returned_by = $actor->id;
                $loan->returned_at = now();
                $loan->return_note = $note;
            }
            if ($action === 'reject') {
                $loan->rejection_note = $note;
            }
            $loan->save();
        });
    }

    public function transitionRequest(StockRequest $request, string $action, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $action, $actor, $note) {
            $request = StockRequest::query()->lockForUpdate()->findOrFail($request->id);
            $item = Item::query()->lockForUpdate()->findOrFail($request->item_id);
            abort_unless($item->location->workflow === Location::WORKFLOW_STOCK, 422);

            $next = match ([$request->status, $action]) {
                ['submitted', 'approve'] => 'approved',
                ['submitted', 'reject'] => 'rejected',
                ['approved', 'fulfill'] => 'fulfilled',
                default => throw ValidationException::withMessages(['status' => 'Aksi tidak berlaku untuk status permintaan ini.']),
            };

            if ($action === 'reject' && blank($note)) {
                throw ValidationException::withMessages(['note' => 'Alasan penolakan wajib diisi.']);
            }
            if ($action === 'fulfill') {
                if ($item->quantity < $request->quantity) {
                    throw ValidationException::withMessages(['quantity' => 'Stok tersedia tidak mencukupi.']);
                }
                $item->decrement('quantity', $request->quantity);
                StockMovement::create([
                    'item_id' => $item->id, 'actor_id' => $actor->id,
                    'stock_request_id' => $request->id, 'change' => -$request->quantity,
                    'balance_after' => $item->quantity, 'type' => 'issued',
                    'note' => 'Permintaan #'.$request->id,
                ]);
            }

            $request->status = $next;
            if ($action === 'approve') {
                $request->approved_by = $actor->id;
                $request->approved_at = now();
            }
            if ($action === 'fulfill') {
                $request->fulfilled_by = $actor->id;
                $request->fulfilled_at = now();
            }
            if ($action === 'reject') {
                $request->rejection_note = $note;
            }
            $request->save();
        });
    }

    public function adjustStock(Item $item, int $change, string $note, User $actor, string $type = 'adjustment'): void
    {
        DB::transaction(function () use ($item, $change, $note, $actor, $type) {
            $item = Item::query()->lockForUpdate()->findOrFail($item->id);
            if ($item->location->workflow !== Location::WORKFLOW_STOCK || $change === 0 || $item->quantity + $change < 0
                || ! in_array($type, ['adjustment', 'in', 'out'], true)
                || ($type === 'in' && $change < 0) || ($type === 'out' && $change > 0)) {
                throw ValidationException::withMessages(['change' => 'Penyesuaian stok tidak valid.']);
            }
            $item->quantity += $change;
            $item->save();
            StockMovement::create([
                'item_id' => $item->id, 'actor_id' => $actor->id,
                'change' => $change, 'balance_after' => $item->quantity,
                'type' => $type, 'note' => $note,
            ]);
        });
    }
}
