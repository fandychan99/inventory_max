<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectionWorkflow
{
    public function record(Location $location, User $actor, array $rows, ?string $note): Inspection
    {
        return DB::transaction(function () use ($location, $actor, $rows, $note) {
            $location = Location::query()->lockForUpdate()->findOrFail($location->id);
            if ($location->workflow !== Location::WORKFLOW_CHECKLIST || ! $location->is_active || $location->archived_at) {
                throw ValidationException::withMessages(['location' => 'Lokasi ini tidak tersedia untuk pengecekan.']);
            }

            $items = $location->items()->where('is_active', true)->orderBy('sku')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Belum ada peralatan aktif untuk diperiksa.']);
            }

            $expectedIds = $items->pluck('id')->map(fn ($id) => (string) $id)->all();
            $submittedIds = array_map('strval', array_keys($rows));
            sort($expectedIds, SORT_STRING);
            sort($submittedIds, SORT_STRING);
            if ($submittedIds !== $expectedIds) {
                throw ValidationException::withMessages(['rows' => 'Daftar peralatan berubah. Muat ulang halaman lalu periksa kembali.']);
            }

            $entries = [];
            $hasIssue = false;
            foreach ($items as $item) {
                $row = $rows[$item->id];
                $actual = (int) $row['actual_quantity'];
                $condition = $row['condition'];
                $entryNote = trim($row['note'] ?? '');
                $issue = $actual !== $item->quantity || $condition !== 'good';
                if ($issue && $entryNote === '') {
                    throw ValidationException::withMessages(["rows.{$item->id}.note" => "Catatan untuk {$item->name} wajib diisi jika jumlah atau kondisinya tidak sesuai."]);
                }
                $hasIssue = $hasIssue || $issue;
                $entries[] = [
                    'item_id' => $item->id,
                    'item_sku' => $item->sku,
                    'item_name' => $item->name,
                    'unit' => $item->unit,
                    'expected_quantity' => $item->quantity,
                    'actual_quantity' => $actual,
                    'condition' => $condition,
                    'note' => $entryNote ?: null,
                ];
            }

            $inspection = Inspection::create([
                'location_id' => $location->id,
                'inspected_by' => $actor->id,
                'inspected_on' => today(),
                'status' => $hasIssue ? 'attention' : 'ok',
                'note' => $note,
            ]);
            $inspection->entries()->createMany($entries);

            return $inspection;
        });
    }
}
