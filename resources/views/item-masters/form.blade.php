@extends('layouts.app')
@section('title', $master->exists ? 'Ubah master barang' : 'Tambah master barang')
@section('content')
<div class="page-head"><div><div class="page-eyebrow">KATALOG · MASTER</div><h1 class="page-title">{{ $master->exists ? 'Ubah master barang' : 'Tambah master barang' }}</h1><p class="page-subtitle">Kode, nama, dan satuan dipakai bersama oleh semua lokasi yang menyimpan barang ini.</p></div><a href="{{ route('item-masters.index') }}" class="btn btn-outline-secondary">Kembali</a></div>
<div class="surface col-xl-8"><form method="post" action="{{ $master->exists ? route('item-masters.update', $master) : route('item-masters.store') }}" class="surface-body">@csrf @if($master->exists)@method('PUT')@endif<div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="sku">Kode barang</label><input id="sku" name="sku" class="form-control" value="{{ old('sku', $master->sku) }}" maxlength="80" pattern="[A-Za-z0-9._\/\-]+" required><p class="form-hint mt-1 mb-0">Kode unik untuk jenis barang ini di seluruh aplikasi.</p></div>
    <div class="col-md-8"><label class="form-label" for="name">Nama barang</label><input id="name" name="name" class="form-control" value="{{ old('name', $master->name) }}" maxlength="255" required></div>
    <div class="col-md-4"><label class="form-label" for="unit">Satuan</label><input id="unit" name="unit" class="form-control" value="{{ old('unit', $master->unit ?: 'unit') }}" maxlength="30" required></div>
    <div class="col-12"><label class="form-label" for="description">Keterangan</label><textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $master->description) }}</textarea></div>
    @if($master->exists)<div class="col-12 form-hint">Perubahan kode, nama, satuan, dan keterangan akan diterapkan ke seluruh lokasi. Label barcode lama perlu dicetak ulang jika kode diubah.</div>@endif
    <div class="col-12"><button class="btn btn-primary" type="submit">Simpan master</button></div>
</div></form></div>
@endsection
