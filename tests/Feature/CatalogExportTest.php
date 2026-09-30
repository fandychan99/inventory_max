<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\User;
use App\Services\InventoryWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class CatalogExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_stock_officer_can_download_one_location_and_filter_an_excel_workbook(): void
    {
        $officer = User::factory()->create()->assignRole('Petugas Gudang B');
        $location = Location::firstWhere('name', 'Gudang B');
        for ($number = 1; $number <= 18; $number++) {
            Item::create([
                'location_id' => $location->id,
                'sku' => 'STK-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'name' => $number === 18 ? '=Contoh & aman' : 'Barang '.$number,
                'unit' => 'pcs', 'quantity' => $number, 'minimum_stock' => 2,
            ]);
        }

        $page = $this->actingAs($officer)->get(route('items.index', ['location' => $location->id]))->assertOk();
        $this->assertSame(1, substr_count($page->getContent(), 'Excel lokasi ini'));
        $this->assertStringNotContainsString('Excel semua stok', $page->getContent());

        $response = $this->get(route('items.export-location', ['location' => $location->id]));
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));

        $sheet = $this->sheet($response->baseResponse->getFile()->getPathname());
        $this->assertSame(19, substr_count($sheet, '<row r="'));
        $this->assertStringContainsString('<c r="F2"><v>1</v></c>', $sheet);
        $this->assertStringContainsString('=Contoh &amp; aman', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);

        $filtered = $this->get(route('items.export-location', ['location' => $location->id, 'q' => 'STK-18']));
        $filtered->assertOk();
        $this->assertSame(2, substr_count($this->sheet($filtered->baseResponse->getFile()->getPathname()), '<row r="'));
    }

    public function test_export_requires_permission_and_excludes_archived_locations(): void
    {
        $applicant = User::factory()->create()->assignRole('Pemohon');
        $admin = User::factory()->create()->assignRole('Administrator');
        $location = Location::firstWhere('name', 'Gudang B');
        Item::create(['location_id' => $location->id, 'sku' => 'STK-ARCH', 'name' => 'Tersimpan', 'unit' => 'pcs', 'quantity' => 3]);

        $this->actingAs($applicant)->get(route('items.export-location', ['location' => $location->id]))->assertForbidden();
        $this->actingAs($admin)->post(route('locations.archive', $location))->assertRedirect();
        $this->get(route('items.export-location', ['location' => $location->id]))->assertNotFound();
        $this->get(route('items.export-location'))->assertSessionHasErrors('location');
    }

    public function test_loan_and_checklist_locations_export_their_own_quantity_columns(): void
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $loanLocation = Location::firstWhere('name', 'Gudang A');
        $tool = Item::create(['location_id' => $loanLocation->id, 'sku' => 'TOOL-01',
            'name' => 'Bor', 'unit' => 'unit', 'quantity' => 3]);
        $loan = Loan::create(['item_id' => $tool->id, 'requester_id' => $admin->id,
            'quantity' => 1, 'needed_from' => today(), 'due_on' => today()->addDay(), 'purpose' => 'Pekerjaan']);
        $workflow = app(InventoryWorkflow::class);
        $workflow->transitionLoan($loan, 'approve', $admin);
        $workflow->transitionLoan($loan, 'issue', $admin);

        $truck = Location::create(['location_type_id' => LocationType::firstWhere('name', 'Truk')->id,
            'name' => 'Fire Truck A', 'workflow' => 'checklist', 'scan_code' => 'TRUCK-01', 'is_active' => true]);
        Item::create(['location_id' => $truck->id, 'sku' => 'HOSE-01',
            'name' => 'Selang', 'unit' => 'unit', 'quantity' => 2]);

        $loanOfficer = User::factory()->create()->assignRole('Petugas Gudang A');
        $checkOfficer = User::factory()->create()->assignRole('Petugas Pemeriksaan');
        foreach ([[$loanOfficer, $loanLocation], [$checkOfficer, $truck]] as [$officer, $location]) {
            $page = $this->actingAs($officer)->get(route('items.index', ['location' => $location->id]))->assertOk();
            $this->assertSame(1, substr_count($page->getContent(), 'Excel lokasi ini'));
        }

        $loanExport = $this->actingAs($loanOfficer)->get(route('items.export-location', ['location' => $loanLocation->id]))->assertOk();
        $loanSheet = $this->sheet($loanExport->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Jumlah total alat', $loanSheet);
        $this->assertStringContainsString('<c r="G2"><v>2</v></c>', $loanSheet);
        $this->assertStringNotContainsString('HOSE-01', $loanSheet);

        $truckExport = $this->actingAs($checkOfficer)->get(route('items.export-location', ['location' => $truck->id]))->assertOk();
        $truckSheet = $this->sheet($truckExport->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Jumlah standar', $truckSheet);
        $this->assertStringContainsString('<c r="F2"><v>2</v></c>', $truckSheet);
        $this->assertStringNotContainsString('TOOL-01', $truckSheet);
    }

    private function sheet(string $path): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        try {
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertIsString($sheet);

            return $sheet;
        } finally {
            $zip->close();
            @unlink($path);
        }
    }
}
