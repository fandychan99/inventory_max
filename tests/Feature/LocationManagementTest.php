<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\LocationType;
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
}
