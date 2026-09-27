<?php

return [
    'initial_admin_email' => env('INITIAL_ADMIN_EMAIL'),
    'initial_admin_password' => env('INITIAL_ADMIN_PASSWORD'),
    'permission_labels' => [
        'dashboard.view' => 'Lihat ringkasan',
        'items.view' => 'Lihat katalog barang',
        'items.manage' => 'Kelola data barang',
        'stock.adjust' => 'Sesuaikan stok lokasi permintaan',
        'loans.create' => 'Ajukan peminjaman',
        'loans.view-all' => 'Lihat semua peminjaman',
        'loans.approve' => 'Setujui / tolak peminjaman',
        'loans.handover' => 'Catat serah terima / pengembalian',
        'requests.create' => 'Ajukan permintaan',
        'requests.view-all' => 'Lihat semua permintaan',
        'requests.approve' => 'Setujui / tolak permintaan',
        'requests.fulfill' => 'Keluarkan barang dari lokasi permintaan',
        'access.manage' => 'Kelola akun, role, dan izin',
        'locations.manage' => 'Kelola jenis dan nama lokasi',
    ],
];
