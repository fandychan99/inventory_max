@extends('layouts.app')
@section('title', 'Barang '.$location->name)
@section('content')
<div class="page-head">
    <div><div class="page-eyebrow">{{ strtoupper($location->type->name) }} · {{ strtoupper($location->name) }}</div><h1 class="page-title">{{ $location->name }}</h1><p class="page-subtitle">{{ match($location->workflow) { 'loan' => 'Peralatan di lokasi ini dipinjam dan dikembalikan.', 'stock' => 'Saldo stok diperbarui melalui mutasi dan pemenuhan permintaan.', 'checklist' => 'Jumlah standar peralatan untuk pengecekan rutin di lokasi ini.' } }}</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if($location->workflow === 'checklist')@canany(['checks.view', 'checks.perform'])<a href="{{ route('inspections.create', $location) }}" class="btn btn-outline-primary">Buka checklist</a>@endcanany @endif
        @can('stock.export')<a href="{{ route('items.export-location', ['location' => $location->id, 'q' => request('q')]) }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-excel me-1"></i> Excel lokasi ini</a>@endcan
        @can('items.manage')@if($location->is_active)<a href="{{ route('items.create', ['location' => $location->id]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Tempatkan barang</a>@endif @endcan
    </div>
</div>
<div class="surface">
    <div class="surface-head"><h2>Daftar barang</h2><form class="d-flex gap-2" method="get"><input type="hidden" name="location" value="{{ $location->id }}"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Nama atau kode" aria-label="Cari barang"><button class="btn btn-sm btn-outline-secondary" type="submit">Cari</button></form></div>
    <div class="table-responsive"><table class="table"><thead><tr><th>Kode</th><th>Nama</th><th>{{ $location->workflow === 'checklist' ? 'Jumlah standar' : 'Total / stok' }}</th>@if($location->workflow !== 'checklist')<th>Tersedia</th>@endif<th>Status</th><th></th></tr></thead><tbody>
        @forelse($items as $item)
            <tr><td class="text-secondary">{{ $item->sku }}</td><td><strong>{{ $item->name }}</strong><div class="form-hint">{{ $item->unit }}</div></td><td>{{ $item->quantity }} {{ $item->unit }}</td>@if($location->workflow !== 'checklist')<td>{{ $item->available }} {{ $item->unit }}</td>@endif<td>@if(!$item->is_active)<span class="badge-status is-muted">Nonaktif</span>@elseif($location->workflow === 'stock' && $item->quantity <= $item->minimum_stock)<span class="badge-status is-warm">Stok menipis</span>@else<span class="badge-status">Aktif</span>@endif</td><td class="text-end text-nowrap">@if($location->workflow !== 'checklist')<a href="{{ route('items.label', $item) }}" class="btn btn-sm btn-outline-secondary">Label</a>@endif @if($location->workflow === 'stock')<a href="{{ route('items.movements', $item) }}" class="btn btn-sm btn-outline-secondary">Mutasi</a>@endif @can('items.manage')<a href="{{ route('items.edit', $item) }}" class="btn btn-sm btn-outline-primary">Ubah</a>@endcan</td></tr>
        @empty
            <tr><td colspan="{{ $location->workflow === 'checklist' ? 5 : 6 }}" class="empty-state"><i class="bi bi-inboxes"></i>Belum ada barang di lokasi ini.</td></tr>
        @endforelse
    </tbody></table></div><div class="p-3">{{ $items->links() }}</div>
</div>
@endsection
