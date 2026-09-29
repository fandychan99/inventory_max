@extends('layouts.app')
@section('title', $item->exists ? 'Ubah barang' : 'Tambah barang')
@section('content')
<div class="page-head"><div><div class="page-eyebrow">{{ strtoupper($location->name) }} · KATALOG</div><h1 class="page-title">{{ $item->exists ? 'Ubah barang' : 'Tambah barang' }}</h1><p class="page-subtitle">{{ match($location->workflow) { 'loan' => 'Tetapkan jumlah total alat yang dapat dipinjam.', 'stock' => $item->exists ? 'Perubahan angka stok dicatat dalam riwayat mutasi.' : 'Stok awal dicatat melalui mutasi setelah barang dibuat.', 'checklist' => 'Tetapkan jumlah standar yang harus ada setiap kali diperiksa.' } }}</p></div><a href="{{ route('items.index', ['location' => $location->id]) }}" class="btn btn-outline-secondary">Kembali</a></div>
<div class="surface col-xl-8"><form method="post" action="{{ $item->exists ? route('items.update', $item) : route('items.store') }}" class="surface-body">
    @csrf @if($item->exists)@method('PUT')@endif
    <input type="hidden" name="location_id" value="{{ $location->id }}">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="sku">Kode barang</label><input id="sku" name="sku" class="form-control" value="{{ old('sku', $item->sku) }}" maxlength="80" pattern="[A-Za-z0-9._\/\-]+" aria-describedby="sku-hint" required><p class="form-hint mt-1 mb-0" id="sku-hint">Huruf, angka, titik, garis bawah, garis miring, atau tanda hubung.</p></div>
        <div class="col-md-8"><label class="form-label" for="name">Nama barang</label><input id="name" name="name" class="form-control" value="{{ old('name', $item->name) }}" required></div>
        <div class="col-md-4"><label class="form-label" for="unit">Satuan</label><input id="unit" name="unit" class="form-control" value="{{ old('unit', $item->unit ?: 'unit') }}" required></div>
        <div class="col-md-4"><label class="form-label" for="quantity">{{ match($location->workflow) { 'loan' => 'Jumlah total alat', 'stock' => 'Stok saat ini', 'checklist' => 'Jumlah standar peralatan' } }}</label><input id="quantity" name="quantity" type="number" min="0" class="form-control" value="{{ old('quantity', $item->quantity ?? 0) }}" {{ $location->workflow === 'stock' && (! $item->exists || ! auth()->user()->can('stock.adjust')) ? 'readonly' : '' }} required></div>
        <div class="col-md-4"><label class="form-label" for="minimum_stock">Batas minimum</label><input id="minimum_stock" name="minimum_stock" type="number" min="0" class="form-control" value="{{ old('minimum_stock', $item->minimum_stock ?? 0) }}" required><p class="form-hint mt-1 mb-0">{{ $location->workflow === 'checklist' ? 'Tidak dipakai dalam hasil checklist.' : '' }}</p></div>
        <div class="col-12"><label class="form-label" for="description">Keterangan</label><textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $item->description) }}</textarea></div>
        @if($location->workflow === 'stock' && $item->exists)
            <div class="col-12"><label class="form-label" for="stock_change_note">Alasan perubahan stok</label><input id="stock_change_note" name="stock_change_note" class="form-control" value="{{ old('stock_change_note') }}" maxlength="255" placeholder="Contoh: hasil stok opname 30 September" @cannot('stock.adjust') readonly @endcannot><p class="form-hint mt-1 mb-0">Wajib bila angka stok diubah. Selisihnya akan tercatat pada <a href="{{ route('items.movements', $item) }}">riwayat mutasi</a>.</p></div>
        @endif
        @if($item->exists)<div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $item->is_active) ? 'checked' : '' }}><span class="form-check-label">{{ $location->workflow === 'checklist' ? 'Peralatan aktif dan tampil di checklist' : 'Barang aktif dan dapat diajukan' }}</span></label></div>@endif
        <div class="col-12"><button class="btn btn-primary" type="submit">Simpan barang</button></div>
    </div>
</form></div>
@endsection
