<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class StockExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_stock_officer_can_download_all_rows_and_filter_an_excel_workbook(): void
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

        $response = $this->actingAs($officer)->get(route('items.export-stock', ['location' => $location->id]));
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));

        $sheet = $this->sheet($response->baseResponse->getFile()->getPathname());
        $this->assertSame(19, substr_count($sheet, '<row r="'));
        $this->assertStringContainsString('<c r="F2"><v>1</v></c>', $sheet);
        $this->assertStringContainsString('=Contoh &amp; aman', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);

        $filtered = $this->get(route('items.export-stock', ['location' => $location->id, 'q' => 'STK-18']));
        $filtered->assertOk();
        $this->assertSame(2, substr_count($this->sheet($filtered->baseResponse->getFile()->getPathname()), '<row r="'));
    }

    public function test_export_requires_permission_and_excludes_archived_locations(): void
    {
        $applicant = User::factory()->create()->assignRole('Pemohon');
        $admin = User::factory()->create()->assignRole('Administrator');
        $location = Location::firstWhere('name', 'Gudang B');
        Item::create(['location_id' => $location->id, 'sku' => 'STK-ARCH', 'name' => 'Tersimpan', 'unit' => 'pcs', 'quantity' => 3]);

        $this->actingAs($applicant)->get(route('items.export-stock'))->assertForbidden();
        $this->actingAs($admin)->post(route('locations.archive', $location))->assertRedirect();
        $this->get(route('items.export-stock', ['location' => $location->id]))->assertNotFound();
        $response = $this->get(route('items.export-stock'));
        $response->assertOk();
        $this->assertStringNotContainsString('STK-ARCH', $this->sheet($response->baseResponse->getFile()->getPathname()));
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
