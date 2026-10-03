@extends('layouts.app')
@section('title', 'Master barang')
@section('content')
<div class="page-head">
    <div><div class="page-eyebrow">KATALOG · MASTER</div><h1 class="page-title">Master barang</h1><p class="page-subtitle">Buat satu kode dan identitas barang, lalu tempatkan barang yang sama di beberapa lokasi.</p></div>
    <div class="d-flex flex-wrap gap-2">
        @can('items.delete-master')<a href="{{ route('item-masters.index', ['archived' => $archived ? null : 1]) }}" class="btn btn-outline-secondary">{{ $archived ? 'Master aktif' : 'Arsip master' }}</a>@endcan
        @if(! $archived)@can('items.manage')<a href="{{ route('item-masters.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Tambah master</a>@endcan @endif
    </div>
</div>
<div class="surface">
    <div class="surface-head"><h2>{{ $archived ? 'Arsip master' : 'Daftar master' }}</h2></div>
    <div class="surface-body border-bottom"><form method="get" class="row g-2">
        @if($archived)<input type="hidden" name="archived" value="1">@endif
        <div class="col-md-8"><label class="visually-hidden" for="master-search">Cari kode atau nama</label><input id="master-search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Cari kode atau nama barang"></div>
        <div class="col-md-auto"><button class="btn btn-outline-primary" type="submit">Cari</button></div>
    </form></div>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Kode</th><th>Nama barang</th><th>Satuan</th><th>Lokasi aktif</th><th></th></tr></thead>
        <tbody>
            @forelse($masters as $master)
                <tr>
                    <td class="text-secondary">{{ $master->sku }}</td>
                    <td><strong>{{ $master->name }}</strong></td>
                    <td>{{ $master->unit }}</td>
                    <td>{{ $master->items_count }}</td>
                    <td class="text-end text-nowrap">
                        @if($archived)
                            @can('items.delete-master')<form class="d-inline" method="post" action="{{ route('item-masters.restore', $master->id) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Pulihkan master</button></form>@endcan
                        @else
                            @can('items.manage')<a class="btn btn-sm btn-outline-primary" href="{{ route('item-masters.edit', $master) }}">Ubah</a>@endcan
                            @can('items.delete-master')
                                <form class="d-inline" method="post" action="{{ route('item-masters.destroy', $master) }}" onsubmit="return confirm('Hapus master barang ini dari semua lokasi? Penempatan dengan riwayat akan diarsipkan.');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus master</button>
                                </form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-state">{{ $archived ? 'Belum ada master yang diarsipkan.' : 'Belum ada master barang. Buat master sebelum menempatkan barang ke lokasi.' }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="p-3">{{ $masters->links() }}</div>
</div>
@endsection
