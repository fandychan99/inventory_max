<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemMaster;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_one_master_can_be_placed_in_multiple_locations_and_scan_requires_a_location_choice(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $loanLocation = Location::firstWhere('name', 'Gudang A');
        $stockLocation = Location::firstWhere('name', 'Gudang B');

        $this->actingAs($admin)->post(route('item-masters.store'), [
            'sku' => 'SELANG', 'name' => 'Selang Pemadam', 'unit' => 'unit',
        ])->assertRedirect();
        $master = ItemMaster::firstWhere('sku', 'SELANG');
        $this->assertNotNull($master);

        $this->post(route('items.store'), [
            'location_id' => $loanLocation->id, 'master_item_id' => $master->id,
            'quantity' => 2, 'minimum_stock' => 0,
        ])->assertRedirect();
        $this->post(route('items.store'), [
            'location_id' => $stockLocation->id, 'master_item_id' => $master->id,
            'quantity' => 5, 'minimum_stock' => 1,
        ])->assertRedirect();

        $this->assertSame(1, ItemMaster::count());
        $this->assertSame(2, Item::where('sku', 'SELANG')->count());
        $loanItem = Item::where('location_id', $loanLocation->id)->firstOrFail();
        $stockItem = Item::where('location_id', $stockLocation->id)->firstOrFail();
        $this->assertSame(2, $loanItem->quantity);
        $this->assertSame(5, $stockItem->quantity);
        $this->assertDatabaseHas('stock_movements', ['item_id' => $stockItem->id, 'change' => 5]);

        $this->get(route('scan.index', ['code' => 'SELANG']))
            ->assertOk()->assertSee('Pilih lokasi barang')->assertSee('Gudang A')->assertSee('Gudang B')
            ->assertDontSee('Catat mutasi stok');
        $this->get(route('scan.index', ['code' => 'SELANG', 'item' => $stockItem->id]))
            ->assertOk()->assertSee('Gudang B')->assertSee('Catat mutasi stok');
        $this->get(route('scan.index', ['code' => 'SELANG', 'item' => 999999]))->assertNotFound();

        $this->post(route('items.store'), [
            'location_id' => $stockLocation->id, 'master_item_id' => $master->id,
            'quantity' => 1, 'minimum_stock' => 0,
        ])->assertSessionHasErrors('master_item_id');
        $this->assertSame(2, Item::count());
    }

    public function test_master_changes_sync_all_locations_and_code_cannot_clash_with_a_location_barcode(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $master = ItemMaster::create(['sku' => 'HOSE', 'name' => 'Selang', 'unit' => 'unit']);
        $first = Item::create(['location_id' => Location::firstWhere('name', 'Gudang A')->id,
            'master_item_id' => $master->id, 'quantity' => 2]);
        $second = Item::create(['location_id' => Location::firstWhere('name', 'Gudang B')->id,
            'master_item_id' => $master->id, 'quantity' => 4]);

        $this->actingAs($admin)->put(route('item-masters.update', $master), [
            'sku' => 'HOSE-NEW', 'name' => 'Selang Pemadam', 'unit' => 'roll',
            'description' => 'Ukuran standar',
        ])->assertRedirect();
        foreach ([$first, $second] as $item) {
            $item->refresh();
            $this->assertSame('HOSE-NEW', $item->sku);
            $this->assertSame('Selang Pemadam', $item->name);
            $this->assertSame('roll', $item->unit);
            $this->assertSame('Ukuran standar', $item->description);
        }

        $this->get(route('scan.index', ['code' => 'HOSE']))->assertSee('KODE TIDAK DITEMUKAN');
        $this->get(route('scan.index', ['code' => 'HOSE-NEW']))->assertSee('Pilih lokasi barang');

        $this->post(route('locations.store'), [
            'location_type_id' => Location::first()->location_type_id,
            'name' => 'Truk Baru', 'workflow' => 'checklist', 'scan_code' => 'TRUCK-CODE',
        ])->assertRedirect();
        $this->put(route('item-masters.update', $master), [
            'sku' => 'TRUCK-CODE', 'name' => 'Selang Pemadam', 'unit' => 'roll',
        ])->assertSessionHasErrors('sku');
        $this->assertSame('HOSE-NEW', $master->fresh()->sku);
    }

    public function test_identical_equipment_codes_in_two_trucks_open_the_selected_checklist(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $typeId = Location::first()->location_type_id;
        $truckA = Location::create(['location_type_id' => $typeId, 'name' => 'Fire Truck A',
            'workflow' => 'checklist', 'scan_code' => 'TRUCK-A', 'is_active' => true]);
        $truckB = Location::create(['location_type_id' => $typeId, 'name' => 'Fire Truck B',
            'workflow' => 'checklist', 'scan_code' => 'TRUCK-B', 'is_active' => true]);
        $master = ItemMaster::create(['sku' => 'SELANG', 'name' => 'Selang Pemadam', 'unit' => 'unit']);
        Item::create(['location_id' => $truckA->id, 'master_item_id' => $master->id, 'quantity' => 2]);
        $itemB = Item::create(['location_id' => $truckB->id, 'master_item_id' => $master->id, 'quantity' => 2]);

        $this->actingAs($admin)->get(route('scan.index', ['code' => 'SELANG']))
            ->assertOk()->assertSee('Fire Truck A')->assertSee('Fire Truck B');
        $this->get(route('scan.index', ['code' => 'SELANG', 'item' => $itemB->id]))
            ->assertRedirect(route('inspections.create', $truckB));
        $this->get(route('scan.index', ['code' => 'TRUCK-A']))
            ->assertRedirect(route('inspections.create', $truckA));
    }

    public function test_viewers_cannot_modify_master_data(): void
    {
        $viewer = User::factory()->create()->assignRole('Pemohon');
        $this->actingAs($viewer)->get(route('item-masters.index'))->assertOk();
        $this->post(route('item-masters.store'), [
            'sku' => 'NOPE', 'name' => 'Tidak boleh', 'unit' => 'unit',
        ])->assertForbidden();

        $manager = User::factory()->create()->givePermissionTo('items.manage');
        $this->actingAs($manager)->get(route('item-masters.index'))->assertOk();
    }
}
