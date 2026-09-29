<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_create_a_type_and_stock_location_with_its_own_catalog(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->post(route('location-types.store'), ['name' => 'Kontainer'])->assertRedirect();
        $type = LocationType::firstWhere('name', 'Kontainer');
        $this->post(route('locations.store'), [
            'location_type_id' => $type->id, 'name' => 'Kontainer 01', 'workflow' => 'stock',
        ])->assertRedirect();
        $location = Location::firstWhere('name', 'Kontainer 01');

        $this->post(route('items.store'), [
            'location_id' => $location->id, 'sku' => 'K-01', 'name' => 'Lakban',
            'unit' => 'roll', 'quantity' => 20, 'minimum_stock' => 2,
        ])->assertRedirect();
        $item = Item::firstWhere('sku', 'K-01');

        $this->assertSame(0, $item->quantity);
        $this->get(route('items.index', ['location' => $location->id]))->assertOk()->assertSee('Kontainer 01')->assertSee('Lakban');
        $this->get(route('scan.index', ['code' => 'K-01']))->assertOk()->assertSee('Kontainer 01')->assertSee('Stok masuk / keluar langsung');
        $this->get(route('dashboard'))->assertOk()->assertSee('Kontainer 01');
    }

    public function test_new_loan_location_uses_the_loan_workflow(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $type = LocationType::firstWhere('name', 'Lemari');
        $this->actingAs($admin)->post(route('locations.store'), [
            'location_type_id' => $type->id, 'name' => 'Lemari Teknik', 'workflow' => 'loan',
        ])->assertRedirect();
        $location = Location::firstWhere('name', 'Lemari Teknik');
        $this->post(route('items.store'), [
            'location_id' => $location->id, 'sku' => 'L-01', 'name' => 'Multimeter',
            'unit' => 'unit', 'quantity' => 3, 'minimum_stock' => 0,
        ])->assertRedirect();
        $item = Item::firstWhere('sku', 'L-01');

        $this->post(route('loans.store'), [
            'item_id' => $item->id, 'quantity' => 1,
            'needed_from' => today()->toDateString(), 'due_on' => today()->addDay()->toDateString(),
            'purpose' => 'Pemeriksaan',
        ])->assertRedirect();
        $this->assertDatabaseHas('loans', ['item_id' => $item->id, 'status' => 'submitted']);
        $this->get(route('loans.index', ['location' => $location->id]))->assertOk()->assertSee('Lemari Teknik')->assertSee('Multimeter');
    }

    public function test_location_with_items_cannot_change_workflow_and_inactive_location_rejects_new_requests(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $location = Location::firstWhere('name', 'Gudang A');
        $item = Item::create(['location_id' => $location->id, 'sku' => 'A-LOCK', 'name' => 'Tang', 'unit' => 'unit', 'quantity' => 2]);

        $this->actingAs($admin)->put(route('locations.update', $location), [
            'location_type_id' => $location->location_type_id, 'name' => 'Gudang Alat', 'workflow' => 'stock', 'is_active' => '1',
        ])->assertSessionHasErrors('workflow');
        $this->assertSame('loan', $location->fresh()->workflow);

        $this->put(route('locations.update', $location), [
            'location_type_id' => $location->location_type_id, 'name' => 'Gudang Alat', 'workflow' => 'loan',
        ])->assertRedirect();
        $this->assertFalse($location->fresh()->is_active);
        $this->post(route('loans.store'), [
            'item_id' => $item->id, 'quantity' => 1,
            'needed_from' => today()->toDateString(), 'due_on' => today()->addDay()->toDateString(),
            'purpose' => 'Pekerjaan',
        ])->assertStatus(422);
        $this->get(route('items.index', ['location' => $location->id]))->assertOk()->assertSee('Gudang Alat');
    }

    public function test_applicant_cannot_manage_location_masters(): void
    {
        $applicant = User::factory()->create()->assignRole('Pemohon');
        $this->actingAs($applicant)->get(route('locations.index'))->assertForbidden();
        $this->post(route('locations.store'), [
            'location_type_id' => LocationType::first()->id, 'name' => 'Truk Rahasia', 'workflow' => 'stock',
        ])->assertForbidden();
    }

    public function test_archive_hides_a_location_and_type_without_removing_items_and_can_be_restored(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $this->actingAs($admin)->post(route('location-types.store'), ['name' => 'Kontainer QA'])->assertRedirect();
        $type = LocationType::firstWhere('name', 'Kontainer QA');
        $this->post(route('locations.store'), [
            'location_type_id' => $type->id, 'name' => 'Kontainer Arsip', 'workflow' => 'stock',
        ])->assertRedirect();
        $location = Location::firstWhere('name', 'Kontainer Arsip');
        $item = Item::create(['location_id' => $location->id, 'sku' => 'QA-ARCHIVE', 'name' => 'Barang arsip', 'unit' => 'pcs', 'quantity' => 4]);
        $request = StockRequest::create(['item_id' => $item->id, 'requester_id' => $admin->id, 'quantity' => 1, 'purpose' => 'Dipesan sebelum arsip']);
        $this->post(route('requests.transition', [$request, 'approve']))->assertRedirect();

        $this->post(route('location-types.archive', $type))->assertSessionHasErrors('location_type_id');
        $this->post(route('locations.archive', $location))->assertRedirect();
        $this->assertNotNull($location->fresh()->archived_at);
        $this->assertFalse($location->fresh()->is_active);
        $this->assertDatabaseHas('items', ['sku' => 'QA-ARCHIVE', 'location_id' => $location->id]);
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('items.index', ['location' => $location->id]));
        $this->get(route('items.index', ['location' => $location->id]))->assertNotFound();
        $this->get(route('scan.index', ['code' => 'QA-ARCHIVE']))->assertOk()->assertDontSee('Barang arsip');
        $this->post(route('requests.transition', [$request, 'fulfill']))->assertRedirect();
        $this->assertSame('fulfilled', $request->fresh()->status);
        $this->assertSame(3, $item->fresh()->quantity);

        $this->post(route('location-types.archive', $type))->assertRedirect();
        $this->post(route('locations.restore', $location))->assertSessionHasErrors('location_type_id');
        $this->post(route('location-types.restore', $type))->assertRedirect();
        $this->post(route('locations.restore', $location))->assertRedirect();
        $this->assertNull($location->fresh()->archived_at);
        $this->assertFalse($location->fresh()->is_active);
        $this->get(route('items.index', ['location' => $location->id]))->assertOk()->assertSee('Barang arsip');
    }

    public function test_applicant_cannot_archive_locations_or_types(): void
    {
        $applicant = User::factory()->create()->assignRole('Pemohon');
        $this->actingAs($applicant)->post(route('locations.archive', Location::firstWhere('name', 'Gudang A')))->assertForbidden();
        $this->post(route('location-types.archive', LocationType::firstWhere('name', 'Gudang')))->assertForbidden();
    }

    public function test_stock_quantity_can_be_corrected_from_edit_form_with_an_audited_reason(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $location = Location::firstWhere('name', 'Gudang B');
        $item = Item::create(['location_id' => $location->id, 'sku' => 'STK-EDIT', 'name' => 'Masker', 'unit' => 'box', 'quantity' => 5]);
        $form = [
            'location_id' => $location->id, 'sku' => $item->sku, 'name' => $item->name,
            'unit' => $item->unit, 'quantity' => 8, 'minimum_stock' => 0,
            'stock_change_note' => 'Koreksi hasil stok opname', 'is_active' => '1',
        ];

        $this->actingAs($admin)->get(route('items.edit', $item))->assertOk()->assertSee('Alasan perubahan stok');
        $this->put(route('items.update', $item), $form)->assertRedirect();
        $this->assertSame(8, $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id, 'change' => 3, 'balance_after' => 8,
            'note' => 'Koreksi hasil stok opname',
        ]);

        $this->put(route('items.update', $item), [...$form, 'quantity' => 9, 'stock_change_note' => ''])
            ->assertSessionHasErrors('stock_change_note');
        $this->assertSame(8, $item->fresh()->quantity);

        $manager = User::factory()->create()->givePermissionTo(['items.manage', 'items.view']);
        $this->actingAs($manager)->put(route('items.update', $item), [...$form, 'quantity' => 9])->assertForbidden();
        $this->assertSame(8, $item->fresh()->quantity);
    }
}
