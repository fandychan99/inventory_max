<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\InventoryWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_loan_cycle_releases_availability_on_return(): void
    {
        $actor = User::factory()->create();
        $item = Item::create(['location_id' => Location::firstWhere('name', 'Gudang A')->id, 'sku' => 'A-01', 'name' => 'Bor', 'unit' => 'unit', 'quantity' => 2]);
        $loan = Loan::create(['item_id' => $item->id, 'requester_id' => $actor->id, 'quantity' => 2, 'needed_from' => today(), 'due_on' => today()->addDays(2), 'purpose' => 'Pekerjaan']);
        $workflow = app(InventoryWorkflow::class);

        $workflow->transitionLoan($loan, 'approve', $actor);
        $workflow->transitionLoan($loan, 'issue', $actor);
        $this->assertSame(0, $item->fresh()->available);
        $workflow->transitionLoan($loan, 'return', $actor, 'Baik');
        $this->assertSame(2, $item->fresh()->available);
        $this->assertSame('returned', $loan->fresh()->status);
    }

    public function test_request_fulfillment_decrements_stock_once_and_records_movement(): void
    {
        $actor = User::factory()->create();
        $item = Item::create(['location_id' => Location::firstWhere('name', 'Gudang B')->id, 'sku' => 'B-01', 'name' => 'Sarung tangan', 'unit' => 'pasang', 'quantity' => 0]);
        $workflow = app(InventoryWorkflow::class);
        $workflow->adjustStock($item, 5, 'Stok masuk', $actor);
        $request = StockRequest::create(['item_id' => $item->id, 'requester_id' => $actor->id, 'quantity' => 3, 'purpose' => 'Operasional']);
        $workflow->transitionRequest($request, 'approve', $actor);
        $workflow->transitionRequest($request, 'fulfill', $actor);

        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame([-3, 5], $item->movements()->latest('id')->pluck('change')->all());
        $this->expectException(ValidationException::class);
        $workflow->transitionRequest($request, 'fulfill', $actor);
    }

    public function test_low_stock_rejects_fulfillment_without_changing_balance(): void
    {
        $actor = User::factory()->create();
        $item = Item::create(['location_id' => Location::firstWhere('name', 'Gudang B')->id, 'sku' => 'B-02', 'name' => 'Kertas', 'unit' => 'rim', 'quantity' => 1]);
        $request = StockRequest::create(['item_id' => $item->id, 'requester_id' => $actor->id, 'quantity' => 2, 'purpose' => 'Cetak']);
        $workflow = app(InventoryWorkflow::class);
        $workflow->transitionRequest($request, 'approve', $actor);
        try {
            $workflow->transitionRequest($request, 'fulfill', $actor);
            $this->fail('Expected insufficient stock.');
        } catch (ValidationException) {
            $this->assertSame(1, $item->fresh()->quantity);
            $this->assertSame('approved', $request->fresh()->status);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_permissions_hide_management_routes_and_allow_admin_pages(): void
    {
        $applicant = User::factory()->create()->assignRole('Pemohon');
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($applicant)->get(route('access.index'))->assertForbidden();
        $this->actingAs($applicant)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('access.index'))->assertOk();
        $this->actingAs($admin)->get(route('items.index', ['location' => Location::firstWhere('name', 'Gudang A')->id]))->assertOk();
    }

    public function test_primary_blade_pages_render_with_inventory_data(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $tool = Item::create(['location_id' => Location::firstWhere('name', 'Gudang A')->id, 'sku' => 'A-03', 'name' => 'Alat ukur', 'unit' => 'unit', 'quantity' => 3]);
        $supply = Item::create(['location_id' => Location::firstWhere('name', 'Gudang B')->id, 'sku' => 'B-03', 'name' => 'Label', 'unit' => 'lembar', 'quantity' => 0]);
        $loan = Loan::create(['item_id' => $tool->id, 'requester_id' => $admin->id, 'quantity' => 1, 'needed_from' => today(), 'due_on' => today()->addDay(), 'purpose' => 'Inventarisasi']);
        $request = StockRequest::create(['item_id' => $supply->id, 'requester_id' => $admin->id, 'quantity' => 1, 'purpose' => 'Pengemasan']);

        $this->actingAs($admin)->get(route('items.index', ['location' => $supply->location_id]))->assertOk()->assertSee('Label');
        $this->get(route('items.create', ['location' => $tool->location_id]))->assertOk();
        $this->get(route('items.edit', $tool))->assertOk();
        $this->get(route('items.movements', $supply))->assertOk();
        $this->get(route('loans.index'))->assertOk()->assertSee('Alat ukur');
        $this->get(route('loans.create'))->assertOk();
        $this->get(route('loans.show', $loan))->assertOk();
        $this->get(route('requests.index'))->assertOk()->assertSee('Label');
        $this->get(route('requests.create'))->assertOk();
        $this->get(route('requests.show', $request))->assertOk();
    }

    public function test_admin_can_configure_role_permissions_from_ui_route(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)->post(route('access.roles.store'), [
            'name' => 'Koordinator',
            'permissions' => ['dashboard.view', 'loans.approve'],
        ])->assertRedirect();

        $this->assertTrue(Role::findByName('Koordinator')->hasPermissionTo('loans.approve'));
        $this->assertFalse(Role::findByName('Koordinator')->hasPermissionTo('stock.adjust'));
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertRedirect();
        }

        $this->post(route('login.store'), ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_users_can_submit_a_loan_and_stock_request(): void
    {
        $applicant = User::factory()->create()->assignRole('Pemohon');
        $tool = Item::create(['location_id' => Location::firstWhere('name', 'Gudang A')->id, 'sku' => 'A-10', 'name' => 'Tang', 'unit' => 'unit', 'quantity' => 4]);
        $supply = Item::create(['location_id' => Location::firstWhere('name', 'Gudang B')->id, 'sku' => 'B-10', 'name' => 'Baut', 'unit' => 'buah', 'quantity' => 10]);

        $this->actingAs($applicant)->post(route('loans.store'), [
            'item_id' => $tool->id, 'quantity' => 2,
            'needed_from' => today()->toDateString(), 'due_on' => today()->addDay()->toDateString(),
            'purpose' => 'Perbaikan',
        ])->assertRedirect();

        $this->post(route('requests.store'), [
            'item_id' => $supply->id, 'quantity' => 3, 'purpose' => 'Pemasangan',
        ])->assertRedirect();

        $this->assertDatabaseHas('loans', ['item_id' => $tool->id, 'requester_id' => $applicant->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('stock_requests', ['item_id' => $supply->id, 'requester_id' => $applicant->id, 'status' => 'submitted']);
    }
}
