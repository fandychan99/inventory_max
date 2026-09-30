@extends('layouts.app')
@section('title', $item->exists ? 'Ubah barang' : 'Tambah barang')
@section('content')
<div class="page-head"><div><div class="page-eyebrow">{{ strtoupper($location->name) }} · KATALOG</div><h1 class="page-title">{{ $item->exists ? 'Ubah penempatan barang' : 'Tempatkan barang' }}</h1><p class="page-subtitle">{{ match($location->workflow) { 'loan' => 'Tetapkan jumlah total alat yang dapat dipinjam.', 'stock' => $item->exists ? 'Perubahan angka stok dicatat dalam riwayat mutasi.' : 'Isi stok awal saat menempatkan barang; jumlahnya dicatat dalam riwayat mutasi.', 'checklist' => 'Tetapkan jumlah standar yang harus ada setiap kali diperiksa.' } }}</p></div><a href="{{ route('items.index', ['location' => $location->id]) }}" class="btn btn-outline-secondary">Kembali</a></div>
<div class="surface col-xl-8"><form method="post" action="{{ $item->exists ? route('items.update', $item) : route('items.store') }}" class="surface-body">
    @csrf @if($item->exists)@method('PUT')@endif
    <input type="hidden" name="location_id" value="{{ $location->id }}">
    <div class="row g-3">
        @if($item->exists)
            <div class="col-12"><div class="form-label">Master barang</div><div class="fw-semibold">{{ $item->sku }} · {{ $item->name }} ({{ $item->unit }})</div><a class="form-hint" href="{{ route('item-masters.edit', $item->master) }}">Ubah data master barang</a></div>
        @else
            <div class="col-12"><label class="form-label" for="master_item_id">Pilih master barang</label><select id="master_item_id" name="master_item_id" class="form-select" required><option value="">Pilih barang untuk lokasi ini</option>@foreach($masters as $master)<option value="{{ $master->id }}" @selected(old('master_item_id', request('master_item_id')) == $master->id)>{{ $master->sku }} · {{ $master->name }} ({{ $master->unit }})</option>@endforeach</select><p class="form-hint mt-1 mb-0">Barang yang sama dapat ditempatkan di lokasi lain dengan kode yang sama. Belum ada di daftar? <a href="{{ route('item-masters.create') }}">Buat master barang</a> lebih dulu.</p></div>
        @endif
        <div class="col-md-4"><label class="form-label" for="quantity">{{ match($location->workflow) { 'loan' => 'Jumlah total alat', 'stock' => $item->exists ? 'Stok saat ini' : 'Stok awal', 'checklist' => 'Jumlah standar peralatan' } }}</label><input id="quantity" name="quantity" type="number" min="0" class="form-control" value="{{ old('quantity', $item->quantity ?? 0) }}" {{ $location->workflow === 'stock' && ! auth()->user()->can('stock.adjust') ? 'readonly' : '' }} required></div>
        <div class="col-md-4"><label class="form-label" for="minimum_stock">Batas minimum</label><input id="minimum_stock" name="minimum_stock" type="number" min="0" class="form-control" value="{{ old('minimum_stock', $item->minimum_stock ?? 0) }}" required><p class="form-hint mt-1 mb-0">{{ $location->workflow === 'checklist' ? 'Tidak dipakai dalam hasil checklist.' : '' }}</p></div>
        @if($location->workflow === 'stock')
            <div class="col-12"><label class="form-label" for="stock_change_note">{{ $item->exists ? 'Alasan perubahan stok' : 'Catatan stok awal (opsional)' }}</label><input id="stock_change_note" name="stock_change_note" class="form-control" value="{{ old('stock_change_note') }}" maxlength="255" placeholder="{{ $item->exists ? 'Contoh: hasil stok opname 30 September' : 'Contoh: saldo awal hasil perhitungan' }}" @cannot('stock.adjust') readonly @endcannot><p class="form-hint mt-1 mb-0">@if($item->exists) Wajib bila angka stok diubah. Selisihnya akan tercatat pada <a href="{{ route('items.movements', $item) }}">riwayat mutasi</a>. @else Jika kosong, mutasi diberi catatan “Stok awal”. Riwayat mutasi tersedia setelah barang disimpan. @endif</p></div>
        @endif
        @if($item->exists)<div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $item->is_active) ? 'checked' : '' }}><span class="form-check-label">{{ $location->workflow === 'checklist' ? 'Peralatan aktif dan tampil di checklist' : 'Barang aktif dan dapat diajukan' }}</span></label></div>@endif
        <div class="col-12"><button class="btn btn-primary" type="submit">{{ $item->exists ? 'Simpan penempatan' : 'Tempatkan barang' }}</button></div>
    </div>
</form></div>
@endsection
