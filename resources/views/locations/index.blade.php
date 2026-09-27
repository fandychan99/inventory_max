@extends('layouts.app')
@section('title', 'Lokasi & jenis')
@section('content')
<div class="page-head">
    <div>
        <div class="page-eyebrow">ADMINISTRASI · MASTER LOKASI</div>
        <h1 class="page-title">Lokasi & jenis</h1>
        <p class="page-subtitle">Buat jenis seperti Gudang, Truk, atau Lemari, lalu beri nama setiap lokasi dan pilih alur barangnya.</p>
    </div>
</div>

<div class="row g-3 align-items-start">
    <div class="col-xl-4">
        <div class="surface mb-3">
            <div class="surface-head"><h2>Tambah jenis lokasi</h2></div>
            <form method="post" action="{{ route('location-types.store') }}" class="surface-body">
                @csrf
                <label for="type-name" class="form-label">Nama jenis</label>
                <input id="type-name" name="name" class="form-control mb-3" maxlength="80" placeholder="Contoh: Truk" required>
                <button class="btn btn-primary" type="submit">Tambah jenis</button>
            </form>
        </div>
        <div class="surface">
            <div class="surface-head"><h2>Jenis tersedia</h2><span class="form-hint">{{ $types->count() }} jenis</span></div>
            <div class="surface-body">
                @foreach($types as $type)
                    <details class="border-bottom py-2">
                        <summary class="d-flex justify-content-between align-items-center"><strong>{{ $type->name }}</strong><span class="form-hint">{{ $type->locations_count }} lokasi</span></summary>
                        <form method="post" action="{{ route('location-types.update', $type) }}" class="pt-3">
                            @csrf @method('PUT')
                            <label for="type-{{ $type->id }}" class="form-label">Ubah nama jenis</label>
                            <div class="d-flex gap-2"><input id="type-{{ $type->id }}" name="name" class="form-control" value="{{ $type->name }}" maxlength="80" required><button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button></div>
                        </form>
                    </details>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="surface mb-3">
            <div class="surface-head"><h2>Tambah lokasi</h2></div>
            <form method="post" action="{{ route('locations.store') }}" class="surface-body row g-3">
                @csrf
                <div class="col-md-5"><label for="location-name" class="form-label">Nama lokasi</label><input id="location-name" name="name" class="form-control" maxlength="120" placeholder="Contoh: Truk 01" required></div>
                <div class="col-md-3"><label for="location-type" class="form-label">Jenis</label><select id="location-type" name="location_type_id" class="form-select" required><option value="">Pilih jenis</option>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label for="location-workflow" class="form-label">Alur barang</label><select id="location-workflow" name="workflow" class="form-select" required><option value="loan">Pinjam kembali</option><option value="stock">Permintaan stok</option></select></div>
                <div class="col-12"><p class="form-hint mb-2">Alur menentukan apakah barang dipinjam lalu dikembalikan, atau dikeluarkan dari saldo stok setelah permintaan disetujui.</p><button class="btn btn-primary" type="submit">Tambah lokasi</button></div>
            </form>
        </div>
        <div class="surface">
            <div class="surface-head"><h2>Daftar lokasi</h2><span class="form-hint">{{ $locations->count() }} lokasi</span></div>
            <div class="surface-body">
                @forelse($locations as $location)
                    <details class="border-bottom py-3">
                        <summary class="d-flex justify-content-between align-items-center gap-2">
                            <span><strong>{{ $location->name }}</strong><small class="d-block text-secondary">{{ $location->type->name }} · {{ $location->workflow_label }} · {{ $location->items_count }} jenis barang</small></span>
                            <span class="badge-status {{ $location->is_active ? '' : 'is-muted' }}">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </summary>
                        <form method="post" action="{{ route('locations.update', $location) }}" class="pt-3 row g-2">
                            @csrf @method('PUT')
                            <div class="col-md-5"><label for="location-name-{{ $location->id }}" class="form-label">Nama</label><input id="location-name-{{ $location->id }}" name="name" value="{{ $location->name }}" class="form-control" maxlength="120" required></div>
                            <div class="col-md-3"><label for="location-type-{{ $location->id }}" class="form-label">Jenis</label><select id="location-type-{{ $location->id }}" name="location_type_id" class="form-select" required>@foreach($types as $type)<option value="{{ $type->id }}" @selected($location->location_type_id === $type->id)>{{ $type->name }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label for="location-workflow-{{ $location->id }}" class="form-label">Alur</label><select id="location-workflow-{{ $location->id }}" name="workflow" class="form-select" required><option value="loan" @selected($location->workflow === 'loan')>Pinjam kembali</option><option value="stock" @selected($location->workflow === 'stock')>Permintaan stok</option></select></div>
                            <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($location->is_active)><span class="form-check-label">Lokasi aktif untuk barang baru dan pengajuan</span></label></div>
                            <div class="col-12 d-flex align-items-center gap-3"><button class="btn btn-sm btn-primary" type="submit">Simpan lokasi</button><a href="{{ route('items.index', ['location' => $location->id]) }}" class="btn btn-sm btn-outline-secondary">Lihat barang</a></div>
                            @if($location->items_count)<p class="form-hint mb-0">Alur tidak dapat diubah jika lokasi sudah memiliki barang.</p>@endif
                        </form>
                    </details>
                @empty
                    <div class="empty-state">Belum ada lokasi.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
