<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('logo.jpeg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav"><li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Buka menu"><i class="bi bi-list"></i></a></li></ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 d-none d-sm-block"><span class="text-secondary small">{{ auth()->user()->name }}</span></li>
                <li class="nav-item"><form action="{{ route('logout') }}" method="post">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">Keluar</button></form></li>
            </ul>
        </div>
    </nav>
    <aside class="app-sidebar shadow" data-bs-theme="dark">
        <div class="sidebar-brand"><a href="{{ route('dashboard') }}" class="brand-link text-decoration-none"><img class="brand-logo" src="{{ asset('logo.jpeg') }}" alt="Logo TanggapEquip"><span class="brand-text fw-semibold ms-2">{{ config('app.name') }}</span></a></div>
        <div class="sidebar-wrapper"><nav class="mt-3"><ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
            @can('dashboard.view')<li class="nav-item"><a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="nav-icon bi bi-grid-1x2"></i><p>Ringkasan</p></a></li>@endcan
            @canany(['items.view', 'stock.adjust', 'loans.handover', 'requests.fulfill'])<li class="nav-item"><a href="{{ route('scan.index') }}" class="nav-link {{ request()->routeIs('scan.*') ? 'active' : '' }}"><i class="nav-icon bi bi-upc-scan"></i><p>Scan barang</p></a></li>@endcanany
            @can('items.view')
                <li class="nav-header">LOKASI · KATALOG</li>
                @foreach($navigationLocations as $navLocation)
                    <li class="nav-item"><a href="{{ route('items.index', ['location' => $navLocation->id]) }}" class="nav-link {{ request()->routeIs('items.*') && request()->integer('location') === $navLocation->id ? 'active' : '' }}"><i class="nav-icon bi {{ $navLocation->workflow === 'loan' ? 'bi-box-seam' : 'bi-boxes' }}"></i><p>{{ $navLocation->name }}</p></a></li>
                @endforeach
            @endcan
            <li class="nav-header">TRANSAKSI</li>
            @canany(['loans.create', 'loans.view-all'])<li class="nav-item"><a href="{{ route('loans.index') }}" class="nav-link {{ request()->routeIs('loans.*') ? 'active' : '' }}"><i class="nav-icon bi bi-arrow-left-right"></i><p>Peminjaman</p></a></li>@endcanany
            @canany(['requests.create', 'requests.view-all'])<li class="nav-item"><a href="{{ route('requests.index') }}" class="nav-link {{ request()->routeIs('requests.*') ? 'active' : '' }}"><i class="nav-icon bi bi-clipboard-check"></i><p>Permintaan</p></a></li>@endcanany
            @canany(['access.manage', 'locations.manage'])<li class="nav-header">ADMINISTRASI</li>@endcanany
            @can('locations.manage')<li class="nav-item"><a href="{{ route('locations.index') }}" class="nav-link {{ request()->routeIs('locations.*', 'location-types.*') ? 'active' : '' }}"><i class="nav-icon bi bi-geo-alt"></i><p>Lokasi & jenis</p></a></li>@endcan
            @can('access.manage')<li class="nav-item"><a href="{{ route('access.index') }}" class="nav-link {{ request()->routeIs('access.*') ? 'active' : '' }}"><i class="nav-icon bi bi-person-lock"></i><p>Role & akses</p></a></li>@endcan
        </ul></nav></div>
    </aside>
    <main class="app-main"><div class="app-content"><div class="container-fluid px-3 px-lg-4">
        @if (session('success'))<div class="alert alert-success mt-3" role="status">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger mt-3" role="alert"><strong>Periksa kembali data:</strong><ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </div></div></main>
    <footer class="app-footer small text-secondary"><strong>{{ config('app.name') }}</strong> · Pengelolaan lokasi & persediaan</footer>
</div>
</body>
</html>
