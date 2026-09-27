<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\StockRequest;
use App\Models\User;
use App\Services\InventoryWorkflow;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoSeeder extends Seeder
{
    private const PASSWORD = 'Demo12345!';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Data demo hanya boleh dibuat pada lingkungan local atau testing.');
        }

        DB::transaction(function (): void {
            $workflow = app(InventoryWorkflow::class);

            $admin = $this->demoUser('Demo Administrator', 'demo.admin@tanggapequip.test', 'Administrator');
            $loanOfficer = $this->demoUser('Demo Petugas Pinjaman', 'demo.pinjam@tanggapequip.test', 'Petugas Gudang A');
            $stockOfficer = $this->demoUser('Demo Petugas Stok', 'demo.stok@tanggapequip.test', 'Petugas Gudang B');
            $applicant = $this->demoUser('Demo Pemohon', 'demo.pemohon@tanggapequip.test', 'Pemohon');

            $warehouseType = LocationType::firstOrCreate(['name' => 'Gudang']);
            $truckType = LocationType::firstOrCreate(['name' => 'Truk']);
            $cabinetType = LocationType::firstOrCreate(['name' => 'Lemari']);

            $warehouseA = Location::firstOrCreate(['name' => 'Gudang A'], [
                'location_type_id' => $warehouseType->id, 'workflow' => Location::WORKFLOW_LOAN, 'is_active' => true,
            ]);
            $warehouseB = Location::firstOrCreate(['name' => 'Gudang B'], [
                'location_type_id' => $warehouseType->id, 'workflow' => Location::WORKFLOW_STOCK, 'is_active' => true,
            ]);
            $truck = Location::firstOrCreate(['name' => 'DEMO Truk Operasional'], [
                'location_type_id' => $truckType->id, 'workflow' => Location::WORKFLOW_LOAN, 'is_active' => true,
            ]);
            $cabinet = Location::firstOrCreate(['name' => 'DEMO Lemari P3K'], [
                'location_type_id' => $cabinetType->id, 'workflow' => Location::WORKFLOW_STOCK, 'is_active' => true,
            ]);

            $drill = $this->item($warehouseA, 'DEMO-A-BOR', 'Bor listrik', 'unit', 4, 0, $stockOfficer, $workflow);
            $ladder = $this->item($warehouseA, 'DEMO-A-TANGGA', 'Tangga lipat', 'unit', 3, 0, $stockOfficer, $workflow);
            $generator = $this->item($warehouseA, 'DEMO-A-GENSET', 'Generator portabel', 'unit', 2, 0, $stockOfficer, $workflow);
            $gloves = $this->item($warehouseB, 'DEMO-B-SARUNG', 'Sarung tangan kerja', 'pasang', 80, 20, $stockOfficer, $workflow);
            $connector = $this->item($warehouseB, 'DEMO-B-KONEKTOR', 'Konektor kabel', 'buah', 150, 30, $stockOfficer, $workflow);
            $this->item($warehouseB, 'DEMO-B-BATERAI', 'Baterai AA', 'buah', 8, 10, $stockOfficer, $workflow);
            $radio = $this->item($truck, 'DEMO-T-RADIO', 'Radio komunikasi', 'unit', 5, 0, $stockOfficer, $workflow);
            $flashlight = $this->item($truck, 'DEMO-T-SENTER', 'Senter lapangan', 'unit', 8, 0, $stockOfficer, $workflow);
            $bandage = $this->item($cabinet, 'DEMO-L-PERBAN', 'Perban elastis', 'roll', 60, 15, $stockOfficer, $workflow);
            $gauze = $this->item($cabinet, 'DEMO-L-KASA', 'Kasa steril', 'pak', 100, 20, $stockOfficer, $workflow);
            $plaster = $this->item($cabinet, 'DEMO-L-PLESTER', 'Plester luka', 'lembar', 200, 40, $stockOfficer, $workflow);

            $this->loan($drill, $applicant, 1, 'Demo: pengecekan instalasi', today()->addDay(), today()->addDays(3), [], $loanOfficer, $workflow);
            $this->loan($ladder, $applicant, 1, 'Demo: perawatan lampu', today(), today()->addDays(2), ['approve'], $loanOfficer, $workflow);
            $this->loan($radio, $applicant, 2, 'Demo: patroli lapangan', today()->subDays(3), today()->subDay(), ['approve', 'issue'], $loanOfficer, $workflow);
            $this->loan($flashlight, $admin, 1, 'Demo: inspeksi malam', today()->subDays(5), today()->subDays(2), ['approve', 'issue', 'return'], $loanOfficer, $workflow);
            $this->loan($generator, $applicant, 1, 'Demo: kegiatan luar ruang', today()->addDay(), today()->addDays(2), ['reject'], $loanOfficer, $workflow);

            $this->request($connector, $applicant, 25, 'Demo: pemasangan kabel', [], $stockOfficer, $workflow);
            $this->request($bandage, $applicant, 10, 'Demo: isi ulang kotak P3K', ['approve'], $stockOfficer, $workflow);
            $this->request($gloves, $applicant, 12, 'Demo: kebutuhan tim lapangan', ['approve', 'fulfill'], $stockOfficer, $workflow);
            $this->request($plaster, $applicant, 50, 'Demo: permintaan berlebih', ['reject'], $stockOfficer, $workflow);

            // Contoh mutasi langsung via scan, hanya saat barang demo pertama dibuat.
            if ($gauze->wasRecentlyCreated) {
                $workflow->adjustStock($gauze, 5, 'Demo: penerimaan tambahan', $stockOfficer, 'in');
                $workflow->adjustStock($gauze, -2, 'Demo: pemakaian langsung', $stockOfficer, 'out');
            }
        });
    }

    private function demoUser(string $name, string $email, string $role): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name, 'password' => Hash::make(self::PASSWORD),
        ]);
        if ($user->wasRecentlyCreated) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function item(Location $location, string $sku, string $name, string $unit, int $quantity, int $minimumStock, User $actor, InventoryWorkflow $workflow): Item
    {
        if ($location->workflow !== Location::WORKFLOW_LOAN && $location->workflow !== Location::WORKFLOW_STOCK) {
            throw new RuntimeException("Alur lokasi {$location->name} tidak didukung oleh data demo.");
        }

        $item = Item::firstOrCreate(['sku' => $sku], [
            'location_id' => $location->id,
            'name' => $name,
            'unit' => $unit,
            'quantity' => $location->workflow === Location::WORKFLOW_LOAN ? $quantity : 0,
            'minimum_stock' => $minimumStock,
            'description' => 'Data contoh TanggapEquip untuk simulasi operasional.',
            'is_active' => true,
        ]);
        if ($item->location_id !== $location->id) {
            throw new RuntimeException("SKU demo {$sku} sudah dipakai di lokasi lain.");
        }
        if ($item->wasRecentlyCreated && $location->workflow === Location::WORKFLOW_STOCK) {
            $workflow->adjustStock($item, $quantity, 'Demo: stok awal', $actor);
        }

        return $item;
    }

    private function loan(Item $item, User $requester, int $quantity, string $purpose, CarbonInterface $neededFrom, CarbonInterface $dueOn, array $actions, User $officer, InventoryWorkflow $workflow): void
    {
        $loan = Loan::firstOrCreate(
            ['item_id' => $item->id, 'requester_id' => $requester->id, 'purpose' => $purpose],
            ['quantity' => $quantity, 'needed_from' => $neededFrom, 'due_on' => $dueOn],
        );
        if (! $loan->wasRecentlyCreated) {
            return;
        }
        foreach ($actions as $action) {
            $workflow->transitionLoan($loan, $action, $officer, match ($action) {
                'reject' => 'Demo: jadwal pemakaian perlu ditinjau ulang.',
                'return' => 'Demo: alat kembali dalam kondisi baik.',
                default => null,
            });
            $loan->refresh();
        }
    }

    private function request(Item $item, User $requester, int $quantity, string $purpose, array $actions, User $officer, InventoryWorkflow $workflow): void
    {
        $request = StockRequest::firstOrCreate(
            ['item_id' => $item->id, 'requester_id' => $requester->id, 'purpose' => $purpose],
            ['quantity' => $quantity],
        );
        if (! $request->wasRecentlyCreated) {
            return;
        }
        foreach ($actions as $action) {
            $workflow->transitionRequest($request, $action, $officer, $action === 'reject' ? 'Demo: jumlah perlu dikonfirmasi.' : null);
            $request->refresh();
        }
    }
}
