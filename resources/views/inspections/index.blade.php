@extends('layouts.app')
@section('title', 'Pengecekan peralatan')
@section('content')
<div class="page-head"><div><div class="page-eyebrow">OPERASIONAL · CHECKLIST LOKASI</div><h1 class="page-title">Pengecekan peralatan</h1><p class="page-subtitle">Scan satu barcode lokasi untuk membuka seluruh daftar peralatan. Catat jumlah dan kondisi setiap hari.</p></div></div>
<div class="row g-3 mb-4">
    @forelse($locations as $location)
        @php($checkedToday = $location->latestInspection?->inspected_on?->isToday())
        <div class="col-lg-6"><div class="surface h-100">
            <div class="surface-head"><div><div class="page-eyebrow">{{ strtoupper($location->type->name) }} · {{ $location->scan_code }}</div><h2>{{ $location->name }}</h2></div><span class="badge-status {{ !$location->is_active ? 'is-muted' : ($checkedToday && $location->latestInspection->status === 'ok' ? '' : 'is-warm') }}">{{ !$location->is_active ? 'Nonaktif' : ($checkedToday ? ($location->latestInspection->status === 'ok' ? 'Sudah dicek' : 'Perlu tindak lanjut') : 'Belum dicek hari ini') }}</span></div>
            <div class="surface-body"><p class="text-secondary mb-2">{{ $location->active_items_count }} jenis peralatan · Terakhir dicek {{ $location->latestInspection?->created_at?->format('d/m/Y H:i') ?? 'belum pernah' }}</p><div class="d-flex flex-wrap gap-2"><a href="{{ route('inspections.create', $location) }}" class="btn btn-sm btn-primary">Buka checklist</a><a href="{{ route('inspections.label', $location) }}" class="btn btn-sm btn-outline-secondary">Cetak barcode lokasi</a></div></div>
        </div></div>
    @empty
        <div class="col-12"><div class="surface"><div class="surface-body empty-state">Belum ada lokasi dengan alur pengecekan. Administrator dapat membuatnya di menu Lokasi & jenis.</div></div></div>
    @endforelse
</div>
<div class="surface"><div class="surface-head"><h2>Riwayat terbaru</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Waktu</th><th>Lokasi</th><th>Petugas</th><th>Hasil</th><th></th></tr></thead><tbody>
    @forelse($recentInspections as $inspection)<tr><td>{{ $inspection->created_at->format('d/m/Y H:i') }}</td><td>{{ $inspection->location->name }}</td><td>{{ $inspection->inspector->name }}</td><td><span class="badge-status {{ $inspection->status === 'ok' ? '' : 'is-warm' }}">{{ $inspection->status === 'ok' ? 'Sesuai' : 'Perlu tindak lanjut' }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('inspections.show', $inspection) }}">Detail</a></td></tr>
    @empty<tr><td colspan="5" class="empty-state">Belum ada pengecekan.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
