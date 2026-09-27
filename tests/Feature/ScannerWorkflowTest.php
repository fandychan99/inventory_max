<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\InventoryWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScannerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_scanned_stock_in_and_out_keep_an_auditable_balance(): void
    {
        $staff = User::factory()->create()->assignRole('Petugas Gudang B');
        $item = Item::create(['location_id' => Location::firstWhere('name', 'Gudang B')->id, 'sku' => 'B-100', 'name' => 'Kabel', 'unit' => 'meter', 'quantity' => 3]);

        $this->actingAs($staff)->get(route('scan.index', ['code' => $item->sku]))->assertOk()->assertSee('Kabel');
        $this->post(route('scan.stock'), ['item_id' => $item->id, 'direction' => 'in', 'quantity' => 5, 'note' => 'Penerimaan PO-1'])->assertRedirect();
        $this->post(route('scan.stock'), ['item_id' => $item->id, 'direction' => 'out', 'quantity' => 4, 'note' => 'Pemakaian kerja'])->assertRedirect();

        $this->assertSame(4, $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', ['item_id' => $item->id, 'type' => 'in', 'change' => 5, 'balance_after' => 8]);
        $this->assertDatabaseHas('stock_movements', ['item_id' => $item->id, 'type' => 'out', 'change' => -4, 'balance_after' => 4]);
    }

    public function test_scanned_stock_cannot_go_below_zero(): void
    {
        $staff = User::factory()->create()->assignRole('Petugas Gudang B');
        $item = Item::create(['location_id' => Location::firstWhere('name', 'Gudang B')->id, 'sku' => 'B-101', 'name' => 'Baut', 'unit' => 'buah', 'quantity' => 2]);

        $this->actingAs($staff)->post(route('scan.stock'), ['item_id' => $item->id, 'direction' => 'out', 'quantity' => 3, 'note' => 'Pemakaian kerja'])
            ->assertSessionHasErrors('change');
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_scanned_tool_shows_only_actionable_loans_and_uses_existing_flow(): void
    {
        $staff = User::factory()->create()->assignRole('Petugas Gudang A');
        $item = Item::create(['location_id' => Location::firstWhere('name', 'Gudang A')->id, 'sku' => 'A-100', 'name' => 'Bor', 'unit' => 'unit', 'quantity' => 2]);
        $loan = Loan::create(['item_id' => $item->id, 'requester_id' => $staff->id, 'quantity' => 1, 'needed_from' => today(), 'due_on' => today()->addDay(), 'purpose' => 'Perbaikan']);
        app(InventoryWorkflow::class)->transitionLoan($loan, 'approve', $staff);

        $this->actingAs($staff)->get(route('scan.index', ['code' => $item->sku]))->assertOk()->assertSee('Serahkan');
        $this->post(route('loans.transition', [$loan, 'issue']))->assertRedirect();
        $this->get(route('scan.index', ['code' => $item->sku]))->assertOk()->assertSee('Terima kembali');
        $this->post(route('loans.transition', [$loan, 'return']), ['note' => 'Baik'])->assertRedirect();

        $this->assertSame('returned', $loan->fresh()->status);
        $this->assertSame(2, $item->fresh()->available);
    }

    public function test_scanned_supply_can_fulfill_an_approved_request_once(): void
    {
        $staff = User::factory()->create()->assignRole('Petugas Gudang B');
        $item = Item::create(['location_id' => Location::firstWhere('name', 'Gudang B')->id, 'sku' => 'B-102', 'name' => 'Lakban', 'unit' => 'roll', 'quantity' => 5]);
        $stockRequest = StockRequest::create(['item_id' => $item->id, 'requester_id' => $staff->id, 'quantity' => 2, 'purpose' => 'Pengemasan']);
        app(InventoryWorkflow::class)->transitionRequest($stockRequest, 'approve', $staff);

        $this->actingAs($staff)->get(route('scan.index', ['code' => $item->sku]))->assertOk()->assertSee('Keluarkan barang');
        $this->post(route('requests.transition', [$stockRequest, 'fulfill']))->assertRedirect();
        $this->assertSame(3, $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', ['stock_request_id' => $stockRequest->id, 'type' => 'issued', 'change' => -2]);
    }

    public function test_applicant_can_read_a_label_but_cannot_move_stock(): void
    {
        $applicant = User::factory()->create()->assignRole('Pemohon');
        $item = Item::create(['location_id' => Location::firstWhere('name', 'Gudang B')->id, 'sku' => 'B-103', 'name' => 'Spidol', 'unit' => 'buah', 'quantity' => 3]);

        $this->actingAs($applicant)->get(route('items.label', $item))->assertOk()->assertSee('B-103');
        $this->get(route('scan.index', ['code' => 'UNKNOWN']))->assertOk()->assertSee('KODE TIDAK DITEMUKAN');
        $this->post(route('scan.stock'), ['item_id' => $item->id, 'direction' => 'out', 'quantity' => 1, 'note' => 'Coba'])->assertForbidden();
        $this->assertSame(3, $item->fresh()->quantity);
    }
}
