# TanggapEquip

Aplikasi internal berbasis Laravel 12, Blade, MySQL, AdminLTE 4 / Bootstrap 5, dan Spatie Laravel Permission.

## Dokumentasi

- [Panduan penggunaan](docs/PANDUAN_PENGGUNA.md)
- [Panduan kode dan pengembangan](docs/PANDUAN_KODE.md)
- [Use case](docs/USE_CASES.md)
- [Diagram UML](docs/UML.md)

## Lokasi dan alur

- **Master jenis lokasi:** Administrator dapat menambah atau mengubah jenis seperti Gudang, Truk, dan Lemari dari halaman **Lokasi & jenis**.
- **Master lokasi:** Setiap lokasi memiliki nama, jenis, status aktif, dan alur. Contoh: Gudang A, Gudang B, Truk 01, Lemari Peralatan. Jumlah lokasi tidak dibatasi dua. Gudang A dan B dibuat otomatis saat migrasi; barang lama dipetakan ke lokasi tersebut.
- **Pinjam kembali:** pemohon mengajukan alat → petugas menyetujui / menolak → petugas mencatat serah terima → petugas mencatat pengembalian. Unit tersedia dihitung dari total unit dikurangi pinjaman yang sedang berjalan.
- **Permintaan stok:** pemohon mengajukan barang → petugas menyetujui / menolak → petugas mencatat pengeluaran. Stok berkurang saat pengeluaran, dan setiap perubahan stok tercatat dalam mutasi.
- **Akses:** Administrator mengelola akun, role, dan izin dari halaman **Role & akses**. Role bawaan adalah Administrator, Petugas Gudang A, Petugas Gudang B, dan Pemohon.

Nama jenis dan lokasi dapat diubah. Alur lokasi yang sudah memiliki barang dikunci agar riwayat transaksi tetap konsisten. Lokasi dapat dinonaktifkan untuk mencegah barang atau pengajuan baru; riwayat lama tetap dapat dilihat. Kode SKU tetap unik di seluruh lokasi sehingga hasil scan menunjuk ke satu barang.

## Scan barang dan label

1. Buat barang dengan kode SKU yang unik. Pada daftar barang, pilih **Label**, lalu cetak dan tempel label barcode Code 128 pada barang atau rak.
2. Buka **Scan barang**. Scanner USB/Bluetooth yang berfungsi sebagai keyboard dapat mengetik SKU ke kolom kode. Atur scanner agar mengirim tombol **Enter** setelah membaca barcode; jika tidak, tekan **Cari**.
3. Pada HP, buka halaman yang sama dan tekan **Buka kamera**. Izinkan akses kamera, kemudian arahkan ke barcode Code 128 pada label. Kode QR berisi SKU juga dapat dibaca.
4. Hasil scan pada lokasi pinjam kembali menampilkan pinjaman yang disetujui untuk **Serahkan** dan pinjaman berjalan untuk **Terima kembali**. Lokasi permintaan stok menampilkan permintaan yang disetujui untuk **Keluarkan barang**, serta form **Masuk / Keluar** langsung dengan jumlah dan alasan.

Akses kamera browser pada HP memerlukan **HTTPS** dan izin kamera. Alamat `http://127.0.0.1` hanya berlaku pada komputer yang menjalankan server; untuk HP gunakan alamat server yang dapat dijangkau melalui HTTPS. Scanner USB/Bluetooth tetap dapat digunakan tanpa kamera. Halaman scan dibatasi 120 permintaan per menit per pengguna, sedangkan aksi tulis mengikuti batas 30 per menit.

Jika kamera tidak terbuka, halaman menampilkan penyebab umum: perangkat tidak memiliki kamera, izin browser ditolak, atau kamera sedang digunakan aplikasi lain. Pemindai memilih kamera belakang bila tersedia. Komputer preview tanpa webcam tetap dapat memakai scanner USB/Bluetooth.

## Persyaratan

PHP 8.2+, Composer, Node.js, npm, dan MySQL 8 atau MariaDB yang kompatibel.

## Menjalankan dengan MySQL

1. Buat database kosong `inventory_max` di MySQL. Nama dapat diganti.
2. Salin `.env.example` menjadi `.env`, lalu isi `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.
3. Isi `INITIAL_ADMIN_EMAIL` dan `INITIAL_ADMIN_PASSWORD` dengan akun awal yang Anda pilih. Kata sandi minimal 8 karakter disarankan. Jangan gunakan kata sandi contoh atau memasukkan `.env` ke version control.
4. Jalankan:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Buka `http://127.0.0.1:8000`. Setelah akun awal dibuat, kosongkan `INITIAL_ADMIN_PASSWORD` di `.env`. Akun berikutnya dibuat lewat **Role & akses**.

Untuk pengembangan dengan aset yang diperbarui otomatis, jalankan `npm run dev` pada terminal terpisah. Jalankan `php artisan test` untuk menguji alur dan akses.

## Data demo lokal

Setelah `php artisan migrate --seed`, jalankan `php artisan db:seed --class=DemoSeeder` untuk mengisi contoh lokasi, barang, mutasi stok, peminjaman, dan permintaan. Seeder ini hanya berjalan pada `APP_ENV=local` atau `testing`, tidak menghapus data yang ada, dan aman dijalankan ulang.

Empat akun demo memakai kata sandi **`Demo12345!`**:

| Role | Email |
| --- | --- |
| Administrator | `demo.admin@tanggapequip.test` |
| Petugas Gudang A | `demo.pinjam@tanggapequip.test` |
| Petugas Gudang B | `demo.stok@tanggapequip.test` |
| Pemohon | `demo.pemohon@tanggapequip.test` |

Akun dan SKU demo memakai penanda `demo` / `DEMO-` agar mudah dibedakan dari data operasional. Jangan jalankan seeder demo pada server yang digunakan pengguna nyata.

## Catatan data

Setiap barang berada pada satu lokasi. Barang pada lokasi permintaan stok dibuat dengan saldo 0; tambah stok melalui halaman **Mutasi** agar saldo awal tercatat. Barang tidak dihapus, tetapi dapat dinonaktifkan. Permintaan atau pinjaman lama tetap menyimpan referensi barang.

Setiap pengajuan memuat satu jenis barang dan jumlah unit. Penolakan memerlukan alasan. Pengeluaran stok serta serah terima alat memeriksa ulang ketersediaan di dalam transaksi database sehingga dua permintaan bersamaan tidak mengurangi stok di bawah nol. Login dibatasi 5 percobaan per menit per email dan IP; aksi tulis dibatasi 30 per menit per pengguna.

## Struktur utama

- `app/Services/InventoryWorkflow.php`: aturan perubahan status, ketersediaan, dan mutasi stok.
- `app/Http/Controllers/`: validasi, otorisasi, dan halaman.
- `database/migrations/`: struktur MySQL.
- `database/seeders/DatabaseSeeder.php`: izin dan role bawaan, serta akun admin awal dari `.env`.
- `resources/views/`: Blade untuk dashboard, master lokasi, dan alur transaksi.
- `resources/css/app.css`: tema operasi gudang di atas AdminLTE 4.

Desain mengacu pada [Hallmark](https://github.com/Nutlope/hallmark) untuk hierarki yang jelas, konten jujur, dan tampilan responsif tanpa dekorasi yang tidak membantu pekerjaan.
