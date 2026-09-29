<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Masuk · {{ config('app.name') }}</title><link rel="icon" type="image/jpeg" href="{{ asset('logo.jpeg') }}">@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
<main class="auth-wrap">
    <section class="auth-intro"><div class="d-flex align-items-center gap-2"><img class="brand-logo" src="{{ asset('logo.jpeg') }}" alt="Logo TanggapEquip"><strong>{{ config('app.name') }}</strong></div><div><div class="page-eyebrow text-white-50">SISTEM OPERASIONAL INVENTARIS</div><h1>Setiap lokasi.<br>Satu kendali.</h1><p class="mt-4">Pantau peminjaman, permintaan stok, dan pengecekan peralatan di gudang, truk, atau lemari dalam satu tempat.</p></div><small>Pinjam kembali &nbsp;&nbsp; / &nbsp;&nbsp; Permintaan stok &nbsp;&nbsp; / &nbsp;&nbsp; Pengecekan</small></section>
    <section class="auth-panel"><form action="{{ route('login.store') }}" method="post">@csrf<div class="page-eyebrow">AKSES AKUN</div><h2 class="page-title mb-2">Masuk</h2><p class="text-secondary mb-4">Gunakan akun yang diberikan administrator.</p>
        @if ($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif
        <div class="mb-3"><label for="email" class="form-label">Email</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
        <div class="mb-3"><label for="password" class="form-label">Kata sandi</label><input id="password" name="password" type="password" class="form-control" required autocomplete="current-password"></div>
        <label class="form-check mb-4"><input type="checkbox" name="remember" class="form-check-input"><span class="form-check-label">Ingat saya</span></label>
        <button class="btn btn-primary w-100 py-2" type="submit">Masuk ke dashboard <i class="bi bi-arrow-right ms-1"></i></button>
    </form></section>
</main>
</body></html>
