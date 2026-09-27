# Panduan kode dan pengembangan TanggapEquip

Dokumen ini memetakan implementasi yang ada pada repositori, bukan rancangan fitur yang belum dibuat.

## Teknologi dan persiapan

- Backend: PHP 8.2+, Laravel 12, Eloquent, Blade, MySQL. Role/permission: Spatie Laravel Permission 6.
- Frontend: AdminLTE 4, Bootstrap 5, Bootstrap Icons, Vite 6, `html5-qrcode` untuk scan kamera, `JsBarcode` untuk label Code 128.
- Pengujian: PHPUnit melalui `php artisan test`; pengujian fitur berada di `tests/Feature/`.

Untuk instalasi MySQL lokal, buat database kosong, salin `.env.example` menjadi `.env`, isi kredensial `DB_*`, lalu isi `INITIAL_ADMIN_EMAIL` dan `INITIAL_ADMIN_PASSWORD` untuk pembuatan akun awal. Jalankan dari akar proyek:

```powershell
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Gunakan `npm run dev` pada terminal lain jika sedang mengubah aset. Setelah seeder berhasil membuat akun awal, hapus nilai `INITIAL_ADMIN_PASSWORD` dari konfigurasi lokal. Jangan masukkan `.env` ke repositori. Seeder hanya membuat akun awal bila kedua nilai tersedia dan email belum ada. Konfigurasi awal MySQL terdapat di `.env.example`; lingkungan preview yang memakai SQLite tidak membuktikan koneksi MySQL pada komputer lain.

Untuk skenario contoh, jalankan `php artisan db:seed --class=DemoSeeder` setelah migrasi dan seeder utama. Seeder demo terpisah ini hanya berjalan pada `local`/`testing`, menggunakan SKU `DEMO-`, dan aman dijalankan ulang. Akun contoh tercantum di [README](../README.md).

## Peta kode

| Lokasi | Tanggung jawab |
| --- | --- |
| `routes/web.php` | URL, nama route, middleware autentikasi, izin, dan pembatasan laju. |
| `app/Http/Controllers/` | Validasi masukan, otorisasi tambahan, query untuk halaman, redirect. |
| `app/Services/InventoryWorkflow.php` | Transisi status, cek saldo/unit, transaksi database, mutasi. |
| `app/Models/` | Relasi Eloquent dan perhitungan `Item::available`. |
| `database/migrations/` | Skema pengguna, izin Spatie, inventaris, dan migrasi ke lokasi dinamis. |
| `database/seeders/DatabaseSeeder.php` | Daftar izin, role bawaan, serta akun awal dari konfigurasi. |
| `resources/views/` | Tampilan Blade; `layouts/app.blade.php` menentukan menu sesuai izin. |
| `resources/js/` | Inisialisasi AdminLTE/Bootstrap, scan kamera, pembuatan barcode. |
| `resources/css/app.css` | Tema dan aturan cetak label. |
| `tests/Feature/` | Cakupan transaksi, lokasi, scanner, izin, dan rate limit. |

Alur permintaan HTTP: `routes/web.php` → middleware `auth`/`permission`/`throttle` → controller → model atau `InventoryWorkflow` → Blade/redirect. Form Blade memakai CSRF; pembaruan memakai method spoofing `PUT`.

## Model dan data

`LocationType` memiliki banyak `Location`; setiap `Location` punya satu `workflow` (`loan` atau `stock`) dan banyak `Item`. SKU `Item` unik secara global. `Loan` dan `StockRequest` masing-masing menunjuk satu item serta satu pemohon. Keduanya menyimpan pelaku dan waktu persetujuan/tindakan. `StockMovement` mencatat perubahan stok, saldo akhir, petugas, jenis, catatan, dan nomor permintaan bila pengeluaran berasal dari permintaan. User terhubung ke role dan permission melalui tabel Spatie.

Migrasi awal membuat tabel inventaris dengan kolom `warehouse` A/B. Migrasi `2026_09_27_020000_create_locations_and_migrate_items.php` membuat jenis Gudang/Truk/Lemari, lokasi Gudang A (`loan`) dan Gudang B (`stock`), memetakan barang lama, lalu mengganti `warehouse` dengan `location_id`. Jangan mengedit migrasi lama pada sistem yang sudah terpasang; buat migrasi baru untuk perubahan skema berikutnya. `down()` migrasi lokasi menolak rollback jika lokasi sudah berubah atau bertambah.

### Aturan saldo

- Alur `loan`: `items.quantity` adalah total alat; `Item::available` = total dikurangi jumlah seluruh loan berstatus `issued`. Penyerahan tidak mengubah `items.quantity` dan tidak membuat `StockMovement`.
- Alur `stock`: `items.quantity` adalah saldo tersisa; barang baru dibuat dengan saldo 0. Stok awal/koreksi dicatat melalui `adjustStock()`. Pemenuhan permintaan mengurangi saldo dan membuat satu `StockMovement` bertipe `issued`.
- `adjustStock()` mengizinkan tipe `adjustment`, `in`, `out`; perubahan 0, arah perubahan yang tidak sesuai, atau saldo negatif ditolak.

## Aturan alur transaksi

| Entitas | Transisi sah | Pemeriksaan penting |
| --- | --- | --- |
| Pinjaman | `submitted → approved → issued → returned` atau `submitted → rejected` | Penolakan memerlukan alasan. Ketersediaan dicek saat `issue`. |
| Permintaan stok | `submitted → approved → fulfilled` atau `submitted → rejected` | Penolakan memerlukan alasan. Saldo dicek saat `fulfill`. |

`InventoryWorkflow` memakai `DB::transaction()` dan `lockForUpdate()` pada transaksi serta item sebelum perubahan. Transisi yang berulang atau melompati status menghasilkan kesalahan validasi. Controller hanya menerima aksi yang dikenal dan memeriksa izin aksi (`loans.approve`, `loans.handover`, `requests.approve`, `requests.fulfill`). Menambah status/aksi baru berarti memperbarui service, controller, tampilan, label status, dan tes secara bersamaan.

Form pengajuan hanya menerima item aktif dari lokasi aktif dengan alur yang cocok. Pada pinjaman, `needed_from` tidak boleh sebelum hari ini dan `due_on` tidak boleh sebelum `needed_from`. Penolakan wajib memakai `note`. Penambahan item stok memaksa saldo awal 0. Form edit barang stok tidak mengubah saldo; hanya mutasi yang boleh mengubahnya. Total alat pinjam kembali tidak dapat diturunkan di bawah unit yang masih `issued`.

## Hak akses dan pembatasan laju

Seeder mendefinisikan izin `dashboard.view`, `items.view`, `items.manage`, `stock.adjust`, `loans.create`, `loans.view-all`, `loans.approve`, `loans.handover`, `requests.create`, `requests.view-all`, `requests.approve`, `requests.fulfill`, `access.manage`, dan `locations.manage`. Route memakai middleware Spatie; daftar/detail pinjaman dan permintaan juga membatasi data ke pemohon sendiri bila tidak memiliki izin `view-all`. Dashboard menggunakan batas tampilan yang sama untuk transaksi, sedangkan indikator stok menipis dihitung dari seluruh barang stok aktif.

`AccessController` menyediakan UI untuk membuat/mengubah role dan pengguna. Role Administrator dilindungi dari perubahan melalui UI dan disinkronkan dengan semua izin saat seeder dijalankan lagi. Role bawaan lain hanya diberi izin awal saat pertama dibuat, sehingga penyesuaian manual tetap terjaga. Izin sistem ditentukan oleh kode/seeder; UI mengatur pemberian izin yang sudah terdaftar, bukan menciptakan nama izin baru.

`AppServiceProvider` mendefinisikan limiter: login 5/menit per pasangan email+IP, operasi tulis 30/menit per pengguna, dan GET scan 120/menit per pengguna. Route tulis transaksi yang memakai parameter `{action}` memeriksa izinnya di controller. Ketika menambah endpoint, pasang izin dan limiter yang sesuai serta uji akses langsung ke URL, bukan hanya sembunyikan tombol.

## Scan dan label

`resources/js/app.js` memuat modul `scan.js` dan `label.js` hanya bila elemen halaman terkait ada. `scan.js` memanggil `html5-qrcode` setelah pengguna menekan **Buka kamera**, memilih kamera belakang bila ada, menerima Code 128/QR berisi SKU, memvalidasi bentuk SKU, lalu mengirim form GET `/scan?code=...`. Scanner HID USB/Bluetooth memakai input yang sama dan dapat mengirim Enter. Kamera memerlukan secure context/HTTPS pada HP. Pemindaian hanya mencari `Item` berdasarkan SKU; tindakan persediaan/peminjaman tetap memerlukan POST dengan autentikasi, CSRF, izin, dan validasi server. `label.js` menggambar Code 128 pada halaman label dan memanggil `window.print()`.

## Pola perubahan fitur

1. Tentukan apakah fitur berlaku untuk `loan`, `stock`, atau keduanya. Jangan menyimpulkan alur dari nama lokasi; gunakan `Location::workflow`.
2. Bila perlu data baru, buat migrasi, relasi/model, dan aturan validasi. Pertahankan jejak `StockMovement` untuk perubahan saldo stok.
3. Tempatkan transisi atau perubahan saldo di `InventoryWorkflow`, bukan langsung di Blade atau JavaScript. Jaga transaksi dan penguncian item.
4. Tambah izin di seeder dan middleware route, lalu tampilkan aksi dengan `@can`/`@canany`. Periksa akses objek dalam controller untuk detail milik pemohon.
5. Tambah tes fitur untuk transisi sah, status salah, stok kurang, dan akses tanpa izin. Jalankan `php artisan test` serta `npm run build` bila aset berubah.

## Batas fitur saat ini

Akses role belum dibatasi per lokasi; tidak ada transfer barang antar lokasi, peminjaman sebagian per unit bernomor seri, reservasi stok saat disetujui, atau notifikasi otomatis. Satu pengajuan selalu untuk satu jenis barang. Dokumen [use case](USE_CASES.md) dan [UML](UML.md) memodelkan perilaku yang sudah diimplementasikan.
