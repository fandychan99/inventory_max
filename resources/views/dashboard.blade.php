@extends('layouts.app')
@section('title', 'Ringkasan')
@section('content')
<div class="page-head">
    <div><div class="page-eyebrow">OVERVIEW OPERASIONAL</div><h1 class="page-title">Ringkasan lokasi</h1><p class="page-subtitle">Pantau peminjaman, permintaan, dan stok dari semua lokasi.</p></div>
    <span class="text-secondary small">{{ now()->translatedFormat('d F Y') }}</span>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="surface stat stat-emphasis"><div class="stat-label">Sedang dipinjam</div><div class="stat-value">{{ $activeLoans }}</div><div class="stat-foot">Semua lokasi pinjam kembali</div></div></div>
    <div class="col-6 col-xl-3"><div class="surface stat"><div class="stat-label">Terlambat kembali</div><div class="stat-value">{{ $overdueLoans }}</div><div class="stat-foot">Perlu tindak lanjut</div></div></div>
    <div class="col-6 col-xl-3"><div class="surface stat"><div class="stat-label">Permintaan menunggu</div><div class="stat-value">{{ $pendingRequests }}</div><div class="stat-foot">Semua lokasi permintaan stok</div></div></div>
    <div class="col-6 col-xl-3"><div class="surface stat"><div class="stat-label">Stok menipis</div><div class="stat-value">{{ $lowStock }}</div><div class="stat-foot">Pada atau di bawah minimum</div></div></div>
</div>
<div class="d-flex align-items-center justify-content-between mb-2"><h2 class="h5 mb-0">Per lokasi</h2>@can('locations.manage')<a href="{{ route('locations.index') }}" class="btn btn-sm btn-outline-secondary">Kelola lokasi</a>@endcan</div>
<div class="row g-3 mb-4">
    @foreach($locations as $location)
        <div class="col-lg-6"><div class="surface h-100">
            <div class="surface-head"><div><div class="page-eyebrow">{{ strtoupper($location->type->name) }} · {{ strtoupper($location->workflow_label) }}</div><h2>{{ $location->name }}</h2></div><span class="badge-status {{ $location->is_active ? '' : 'is-muted' }}">{{ $location->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
            <div class="surface-body d-flex align-items-center justify-content-between gap-3">
                <div><strong>{{ $location->active_items_count }} jenis barang</strong><div class="form-hint">{{ $location->workflow === 'loan' ? $location->pending_loans_count.' pengajuan menunggu' : $location->pending_requests_count.' permintaan menunggu' }}</div></div>
                @can('items.view')<a href="{{ route('items.index', ['location' => $location->id]) }}" class="btn btn-outline-primary btn-sm">Lihat barang</a>@endcan
            </div>
        </div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-xl-6"><div class="surface"><div class="surface-head"><h2>Peminjaman terbaru</h2>@canany(['loans.create', 'loans.view-all'])<a href="{{ route('loans.index') }}" class="btn btn-sm btn-outline-secondary">Semua</a>@endcanany</div><div class="table-responsive"><table class="table"><thead><tr><th>Alat / lokasi</th><th>Pemohon</th><th>Status</th></tr></thead><tbody>
        @forelse($recentLoans as $loan)<tr><td>@canany(['loans.create', 'loans.view-all'])<a href="{{ route('loans.show', $loan) }}">{{ $loan->item->name }}</a>@else{{ $loan->item->name }}@endcanany<div class="form-hint">{{ $loan->item->location->name }}</div></td><td>{{ $loan->requester->name }}</td><td><span class="badge-status">{{ ucfirst($loan->status) }}</span></td></tr>@empty<tr><td colspan="3" class="empty-state">Belum ada peminjaman.</td></tr>@endforelse
    </tbody></table></div></div></div>
    <div class="col-xl-6"><div class="surface"><div class="surface-head"><h2>Permintaan terbaru</h2>@canany(['requests.create', 'requests.view-all'])<a href="{{ route('requests.index') }}" class="btn btn-sm btn-outline-secondary">Semua</a>@endcanany</div><div class="table-responsive"><table class="table"><thead><tr><th>Barang / lokasi</th><th>Pemohon</th><th>Status</th></tr></thead><tbody>
        @forelse($recentRequests as $stockRequest)<tr><td>@canany(['requests.create', 'requests.view-all'])<a href="{{ route('requests.show', $stockRequest) }}">{{ $stockRequest->item->name }}</a>@else{{ $stockRequest->item->name }}@endcanany<div class="form-hint">{{ $stockRequest->item->location->name }}</div></td><td>{{ $stockRequest->requester->name }}</td><td><span class="badge-status">{{ ucfirst($stockRequest->status) }}</span></td></tr>@empty<tr><td colspan="3" class="empty-state">Belum ada permintaan.</td></tr>@endforelse
    </tbody></table></div></div></div>
</div>
@endsection
