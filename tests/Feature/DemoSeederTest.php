<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\StockRequest;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_complete_scenarios_without_duplication(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);

        $this->assertSame(4, Location::count());
        $this->assertSame(11, Item::count());
        $this->assertSame(5, Loan::count());
        $this->assertSame(4, StockRequest::count());
        $this->assertSame(9, StockMovement::count());
        $this->assertSame(68, Item::where('sku', 'DEMO-B-SARUNG')->firstOrFail()->quantity);
        $this->assertSame(3, Item::where('sku', 'DEMO-T-RADIO')->firstOrFail()->available);
        $this->assertSame(1, Loan::where('status', 'issued')->count());
        $this->assertSame(1, StockRequest::where('status', 'fulfilled')->count());
        $this->assertTrue(Hash::check('Demo12345!', User::where('email', 'demo.pemohon@tanggapequip.test')->firstOrFail()->password));

        $this->seed(DemoSeeder::class);

        $this->assertSame(11, Item::count());
        $this->assertSame(5, Loan::count());
        $this->assertSame(4, StockRequest::count());
        $this->assertSame(9, StockMovement::count());
        $this->assertSame(68, Item::where('sku', 'DEMO-B-SARUNG')->firstOrFail()->quantity);
    }
}
