<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemMaster;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_placement_can_be_removed_without_touching_master_or_other_location(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $type = LocationType::firstWhere('name', 'Truk');
        $first = Location::create(['location_type_id' => $type->id, 'name' => 'Truck 1', 'workflow' => 'checklist', 'is_active' => true]);
        $second = Location::create(['location_type_id' => $type->id, 'name' => 'Truck 2', 'workflow' => 'checklist', 'is_active' => true]);
        $master = ItemMaster::create(['sku' => 'APRON', 'name' => 'Apron', 'unit' => 'unit']);
        $placement = Item::create(['location_id' => $first->id, 'master_item_id' => $master->id, 'quantity' => 0]);
        $other = Item::create(['location_id' => $second->id, 'master_item_id' => $master->id, 'quantity' => 2]);

        $this->actingAs($admin)->get(route('items.index', ['location' => $first->id]))
            ->assertOk()->assertSee('Hapus dari lokasi');
        $this->delete(route('items.destroy', $placement))->assertRedirect();

        $this->assertNull(Item::withTrashed()->find($placement->id));
        $this->assertNotNull(ItemMaster::find($master->id));
        $this->assertNotNull(Item::find($other->id));
        $this->get(route('items.index', ['location' => $first->id]))->assertDontSee('Apron');
        $this->get(route('items.index', ['location' => $second->id]))->assertSee('Apron');
    }

    public function test_historical_placement_is_archived_and_history_survives_restore(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $location = Location::firstWhere('name', 'Gudang B');
        $master = ItemMaster::create(['sku' => 'OLD-STOCK', 'name' => 'Barang lama', 'unit' => 'unit']);
        $placement = Item::create(['location_id' => $location->id, 'master_item_id' => $master->id, 'quantity' => 0]);
        $request = StockRequest::create(['item_id' => $placement->id, 'requester_id' => $admin->id, 'quantity' => 1, 'purpose' => 'Uji riwayat']);
        DB::table('stock_requests')->where('id', $request->id)->update(['status' => 'rejected']);

        $this->actingAs($admin)->delete(route('items.destroy', $placement))->assertRedirect();
        $this->assertTrue(Item::withTrashed()->findOrFail($placement->id)->trashed());
        $this->get(route('items.index', ['location' => $location->id]))->assertDontSee('Barang lama');
        $this->get(route('items.index', ['location' => $location->id, 'archived' => 1]))->assertSee('Barang lama')->assertSee('Pulihkan');
        $this->get(route('requests.show', $request))->assertOk()->assertSee('Barang lama');

        $this->post(route('items.restore', $placement->id))->assertRedirect();
        $this->assertFalse(Item::withTrashed()->findOrFail($placement->id)->trashed());
    }

    public function test_admin_can_remove_master_everywhere_but_manager_cannot(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $manager = User::factory()->create()->givePermissionTo('items.manage');
        $first = Location::firstWhere('name', 'Gudang A');
        $second = Location::firstWhere('name', 'Gudang B');
        $master = ItemMaster::create(['sku' => 'GLOBAL', 'name' => 'Barang global', 'unit' => 'unit']);
        $firstItem = Item::create(['location_id' => $first->id, 'master_item_id' => $master->id, 'quantity' => 0]);
        $secondItem = Item::create(['location_id' => $second->id, 'master_item_id' => $master->id, 'quantity' => 0]);

        $this->actingAs($manager)->get(route('item-masters.index'))->assertOk()->assertDontSee('Hapus master');
        $this->delete(route('item-masters.destroy', $master))->assertForbidden();
        $this->actingAs($admin)->get(route('item-masters.index'))->assertSee('Hapus master');
        $this->delete(route('item-masters.destroy', $master))->assertRedirect();

        $this->assertNull(ItemMaster::withTrashed()->find($master->id));
        $this->assertNull(Item::withTrashed()->find($firstItem->id));
        $this->assertNull(Item::withTrashed()->find($secondItem->id));
    }

    public function test_master_with_history_archives_all_placements_and_can_be_restored(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $first = Location::firstWhere('name', 'Gudang A');
        $second = Location::firstWhere('name', 'Gudang B');
        $master = ItemMaster::create(['sku' => 'HIST', 'name' => 'Barang bersejarah', 'unit' => 'unit']);
        $firstItem = Item::create(['location_id' => $first->id, 'master_item_id' => $master->id, 'quantity' => 0]);
        $secondItem = Item::create(['location_id' => $second->id, 'master_item_id' => $master->id, 'quantity' => 0]);
        $request = StockRequest::create(['item_id' => $secondItem->id, 'requester_id' => $admin->id, 'quantity' => 1, 'purpose' => 'Uji']);
        DB::table('stock_requests')->where('id', $request->id)->update(['status' => 'rejected']);

        $this->actingAs($admin)->delete(route('item-masters.destroy', $master))->assertRedirect();
        $this->assertTrue(ItemMaster::withTrashed()->findOrFail($master->id)->trashed());
        $this->assertNull(Item::withTrashed()->find($firstItem->id));
        $this->assertTrue(Item::withTrashed()->findOrFail($secondItem->id)->trashed());
        $this->get(route('requests.show', $request))->assertOk()->assertSee('Barang bersejarah');

        $this->get(route('item-masters.index', ['archived' => 1]))->assertSee('Barang bersejarah');
        $this->post(route('item-masters.restore', $master->id))->assertRedirect();
        $this->post(route('items.restore', $secondItem->id))->assertRedirect();
        $this->assertNotNull(Item::find($secondItem->id));
    }

    public function test_remaining_stock_and_open_requests_block_removal_atomically(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $stock = Location::firstWhere('name', 'Gudang B');
        $loan = Location::firstWhere('name', 'Gudang A');
        $master = ItemMaster::create(['sku' => 'KEEP', 'name' => 'Jangan hapus', 'unit' => 'unit']);
        $stockItem = Item::create(['location_id' => $stock->id, 'master_item_id' => $master->id, 'quantity' => 3]);
        $loanItem = Item::create(['location_id' => $loan->id, 'master_item_id' => $master->id, 'quantity' => 0]);

        $this->actingAs($admin)->delete(route('items.destroy', $stockItem))->assertSessionHasErrors('remove');
        $this->delete(route('item-masters.destroy', $master))->assertSessionHasErrors('remove');
        $this->assertNotNull(Item::find($loanItem->id));

        $stockItem->update(['quantity' => 0]);
        StockRequest::create(['item_id' => $stockItem->id, 'requester_id' => $admin->id, 'quantity' => 1, 'purpose' => 'Masih berjalan']);
        $this->delete(route('item-masters.destroy', $master))->assertSessionHasErrors('remove');
        $this->assertNotNull(ItemMaster::find($master->id));
        $this->assertNotNull(Item::find($loanItem->id));
    }
}
