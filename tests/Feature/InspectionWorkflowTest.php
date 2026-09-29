<?php

namespace Tests\Feature;

use App\Models\Inspection;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function checklistLocation(): Location
    {
        return Location::create([
            'location_type_id' => LocationType::firstWhere('name', 'Truk')->id,
            'name' => 'Truk Uji', 'workflow' => 'checklist',
            'scan_code' => 'TRUCK-TEST-01', 'is_active' => true,
        ]);
    }

    public function test_one_location_barcode_opens_all_items_and_records_a_snapshot(): void
    {
        $location = $this->checklistLocation();
        $hose = Item::create(['location_id' => $location->id, 'sku' => 'TEST-HOSE', 'name' => 'Selang', 'unit' => 'unit', 'quantity' => 2]);
        $nozzle = Item::create(['location_id' => $location->id, 'sku' => 'TEST-NOZZLE', 'name' => 'Nozzle', 'unit' => 'unit', 'quantity' => 1]);
        $checker = User::factory()->create()->assignRole('Petugas Pemeriksaan');

        $this->actingAs($checker)->get(route('scan.index', ['code' => 'TRUCK-TEST-01']))
            ->assertRedirect(route('inspections.create', $location));
        $this->get(route('inspections.create', $location))->assertOk()->assertSee('Selang')->assertSee('Nozzle');
        $this->post(route('inspections.store', $location), [
            'rows' => [
                $hose->id => ['actual_quantity' => 1, 'condition' => 'good', 'note' => 'Satu selang belum ditemukan.'],
                $nozzle->id => ['actual_quantity' => 1, 'condition' => 'good'],
            ],
            'note' => 'Pemeriksaan pagi',
        ])->assertRedirect();

        $inspection = Inspection::firstOrFail();
        $this->assertSame('attention', $inspection->status);
        $this->assertSame(2, $inspection->entries()->count());
        $this->assertDatabaseHas('inspection_entries', [
            'inspection_id' => $inspection->id, 'item_id' => $hose->id,
            'expected_quantity' => 2, 'actual_quantity' => 1,
        ]);
        $this->assertSame(2, $hose->fresh()->quantity);
        $this->get(route('inspections.show', $inspection))->assertOk()->assertSee('Satu selang belum ditemukan.');

        $hose->update(['name' => 'Selang baru', 'quantity' => 3]);
        $this->assertSame('Selang', $inspection->entries()->where('item_id', $hose->id)->firstOrFail()->item_name);
        $this->assertSame(2, $inspection->entries()->where('item_id', $hose->id)->firstOrFail()->expected_quantity);
    }

    public function test_incomplete_or_unexplained_checklist_is_rejected(): void
    {
        $location = $this->checklistLocation();
        $first = Item::create(['location_id' => $location->id, 'sku' => 'TEST-1', 'name' => 'APAR', 'unit' => 'unit', 'quantity' => 1]);
        $second = Item::create(['location_id' => $location->id, 'sku' => 'TEST-2', 'name' => 'Selang', 'unit' => 'unit', 'quantity' => 2]);
        $checker = User::factory()->create()->assignRole('Petugas Pemeriksaan');

        $this->actingAs($checker)->post(route('inspections.store', $location), [
            'rows' => [$first->id => ['actual_quantity' => 1, 'condition' => 'good']],
        ])->assertSessionHasErrors('rows');
        $this->post(route('inspections.store', $location), [
            'rows' => [
                $first->id => ['actual_quantity' => 1, 'condition' => 'good'],
                $second->id => ['actual_quantity' => 1, 'condition' => 'good'],
            ],
        ])->assertSessionHasErrors("rows.{$second->id}.note");
        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_permissions_and_code_collisions_are_enforced(): void
    {
        $location = $this->checklistLocation();
        $item = Item::create(['location_id' => $location->id, 'sku' => 'TEST-ITEM', 'name' => 'APAR', 'unit' => 'unit', 'quantity' => 1]);
        $applicant = User::factory()->create()->assignRole('Pemohon');
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($applicant)->post(route('inspections.store', $location), [
            'rows' => [$item->id => ['actual_quantity' => 1, 'condition' => 'good']],
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('items.store'), [
            'location_id' => $location->id, 'sku' => 'TRUCK-TEST-01', 'name' => 'Tabrakan kode',
            'unit' => 'unit', 'quantity' => 1, 'minimum_stock' => 0,
        ])->assertSessionHasErrors('sku');
        $this->post(route('locations.store'), [
            'location_type_id' => $location->location_type_id, 'name' => 'Truk Kedua',
            'workflow' => 'checklist', 'scan_code' => 'TEST-ITEM',
        ])->assertSessionHasErrors('scan_code');
        $this->get(route('inspections.label', $location))->assertOk()->assertSee('TRUCK-TEST-01');
    }
}
