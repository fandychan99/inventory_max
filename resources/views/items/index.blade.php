@extends('layouts.app')
@section('title', 'Barang '.$location->name)
@section('content')
<div class="page-head">
    <div><div class="page-eyebrow">{{ strtoupper($location->type->name) }} · {{ strtoupper($location->name) }}</div><h1 class="page-title">{{ $location->name }}</h1><p class="page-subtitle">{{ $location->workflow === 'loan' ? 'Peralatan di lokasi ini dipinjam dan dikembalikan.' : 'Saldo stok diperbarui melalui mutasi dan pemenuhan permintaan.' }}</p></div>
    @can('items.manage')@if($location->is_active)<a href="{{ route('items.create', ['location' => $location->id]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Tambah barang</a>@endif @endcan
</div>
<div class="surface">
    <div class="surface-head"><h2>Daftar barang</h2><form class="d-flex gap-2" method="get"><input type="hidden" name="location" value="{{ $location->id }}"><input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Nama atau kode" aria-label="Cari barang"><button class="btn btn-sm btn-outline-secondary" type="submit">Cari</button></form></div>
    <div class="table-responsive"><table class="table"><thead><tr><th>Kode</th><th>Nama</th><th>Total / stok</th><th>Tersedia</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($items as $item)
            <tr><td class="text-secondary">{{ $item->sku }}</td><td><strong>{{ $item->name }}</strong><div class="form-hint">{{ $item->unit }}</div></td><td>{{ $item->quantity }}</td><td>{{ $item->available }} {{ $item->unit }}</td><td>@if(!$item->is_active)<span class="badge-status is-muted">Nonaktif</span>@elseif($location->workflow === 'stock' && $item->quantity <= $item->minimum_stock)<span class="badge-status is-warm">Stok menipis</span>@else<span class="badge-status">Aktif</span>@endif</td><td class="text-end text-nowrap"><a href="{{ route('items.label', $item) }}" class="btn btn-sm btn-outline-secondary">Label</a> @if($location->workflow === 'stock')<a href="{{ route('items.movements', $item) }}" class="btn btn-sm btn-outline-secondary">Mutasi</a>@endif @can('items.manage')<a href="{{ route('items.edit', $item) }}" class="btn btn-sm btn-outline-primary">Ubah</a>@endcan</td></tr>
        @empty
            <tr><td colspan="6" class="empty-state"><i class="bi bi-inboxes"></i>Belum ada barang di lokasi ini.</td></tr>
        @endforelse
    </tbody></table></div><div class="p-3">{{ $items->links() }}</div>
</div>
@endsection
