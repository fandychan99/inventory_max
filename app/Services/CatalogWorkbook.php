<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Location;
use RuntimeException;
use XMLWriter;
use ZipArchive;

class CatalogWorkbook
{
    /** @param iterable<Item> $items */
    public function create(iterable $items, Location $location): string
    {
        $sheetPath = tempnam(sys_get_temp_dir(), 'te_sheet_');
        $archivePath = tempnam(sys_get_temp_dir(), 'te_stock_');
        if ($sheetPath === false || $archivePath === false) {
            if ($sheetPath !== false) {
                @unlink($sheetPath);
            }
            if ($archivePath !== false) {
                @unlink($archivePath);
            }
            throw new RuntimeException('Berkas sementara ekspor stok tidak dapat dibuat.');
        }

        try {
            $xml = new XMLWriter;
            if (! $xml->openUri($sheetPath)) {
                throw new RuntimeException('Lembar Excel stok tidak dapat dibuat.');
            }
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('worksheet');
            $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xml->writeRaw('<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
            $xml->startElement('sheetData');
            $this->row($xml, 1, $this->headers($location));
            $number = 2;
            foreach ($items as $item) {
                $this->row($xml, $number++, $this->values($item, $location));
            }
            $xml->endElement();
            $xml->endElement();
            $xml->endDocument();
            $xml->flush();
            unset($xml);

            $zip = new ZipArchive;
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Arsip Excel stok tidak dapat dibuat.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $sheetName = match ($location->workflow) {
                Location::WORKFLOW_LOAN => 'Pinjam kembali',
                Location::WORKFLOW_STOCK => 'Stok',
                Location::WORKFLOW_CHECKLIST => 'Pengecekan',
            };
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$sheetName.'" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $sheetAdded = $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
            $archiveClosed = $zip->close();
            if (! $sheetAdded || ! $archiveClosed) {
                throw new RuntimeException('Arsip Excel stok tidak lengkap.');
            }

            return $archivePath;
        } catch (\Throwable $error) {
            @unlink($archivePath);
            throw $error;
        } finally {
            @unlink($sheetPath);
        }
    }

    private function headers(Location $location): array
    {
        $common = ['Kode', 'Nama barang', 'Lokasi', 'Jenis lokasi', 'Satuan'];

        return match ($location->workflow) {
            Location::WORKFLOW_LOAN => [...$common, 'Jumlah total alat', 'Tersedia', 'Status barang'],
            Location::WORKFLOW_STOCK => [...$common, 'Stok saat ini', 'Batas minimum', 'Status barang', 'Status stok'],
            Location::WORKFLOW_CHECKLIST => [...$common, 'Jumlah standar', 'Status barang'],
        };
    }

    private function values(Item $item, Location $location): array
    {
        $common = [$item->sku, $item->name, $item->location->name, $item->location->type->name, $item->unit];
        $status = $item->is_active ? 'Aktif' : 'Nonaktif';

        return match ($location->workflow) {
            Location::WORKFLOW_LOAN => [...$common, (int) $item->quantity,
                max(0, (int) $item->quantity - (int) ($item->issued_quantity ?? 0)), $status],
            Location::WORKFLOW_STOCK => [...$common, (int) $item->quantity, (int) $item->minimum_stock,
                $status, $item->quantity <= $item->minimum_stock ? 'Menipis' : 'Cukup'],
            Location::WORKFLOW_CHECKLIST => [...$common, (int) $item->quantity, $status],
        };
    }

    /** @param array<int, string|int> $values */
    private function row(XMLWriter $xml, int $number, array $values): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);
        foreach ($values as $index => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', chr(65 + $index).$number);
            if (is_int($value)) {
                $xml->writeElement('v', (string) $value);
            } else {
                // Text cells prevent spreadsheet formula execution from item names and codes.
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->writeElement('t', $value);
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
    }
}
