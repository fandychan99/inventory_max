# Use case TanggapEquip

## Ruang lingkup dan aktor

Sistem mengelola banyak lokasi. Setiap lokasi memilih salah satu alur: **pinjam kembali** untuk alat, atau **permintaan stok** untuk barang yang saldonya berkurang saat dikeluarkan. Gudang A/B adalah data awal, bukan batas jumlah lokasi. Scanner USB/Bluetooth dan kamera HP adalah cara memasukkan SKU; keputusan transaksi tetap dibuat pengguna yang telah masuk.

| Aktor | Tanggung jawab |
| --- | --- |
| Pemohon | Melihat katalog, mengajukan pinjaman atau permintaan, memantau pengajuan sendiri. |
| Petugas alur pinjaman | Menilai pengajuan, menyerahkan, dan menerima kembali alat. Role awal: Petugas Gudang A. |
| Petugas alur stok | Menilai permintaan, mencatat stok dan pengeluaran barang. Role awal: Petugas Gudang B. |
| Administrator | Menyiapkan lokasi, barang, pengguna, role, izin, dan melakukan semua tindakan operasional. |

Role di tabel adalah penugasan awal. Sistem memutuskan hak dari **permission**, sehingga Administrator bisa membuat kombinasi role lain.

## Daftar use case

| ID | Use case | Aktor utama | Izin utama | Hasil |
| --- | --- | --- | --- | --- |
| UC-01 | Masuk/keluar | Semua pengguna | Akun terdaftar | Sesi autentikasi dibuat atau diakhiri. |
| UC-02 | Pantau ringkasan | Semua role bawaan | `dashboard.view` | Statistik dan aktivitas terbaru terlihat sesuai cakupan akses transaksi. |
| UC-03 | Kelola jenis dan lokasi | Administrator | `locations.manage` | Jenis/lokasi bertambah atau berubah. |
| UC-04 | Kelola katalog dan label | Administrator atau role khusus | `items.manage`; label: `items.view` | Item tercatat dan label Code 128 dapat dicetak. |
| UC-05 | Ajukan dan pantau pinjaman | Pemohon | `loans.create` | Pinjaman `submitted` tersimpan dan pemohon dapat melihatnya. |
| UC-06 | Putuskan pinjaman | Petugas pinjaman | `loans.approve` | Pinjaman menjadi `approved` atau `rejected`. |
| UC-07 | Serahkan dan terima kembali alat | Petugas pinjaman | `loans.handover` | Pinjaman menjadi `issued`, lalu `returned`; ketersediaan berubah. |
| UC-08 | Ajukan dan pantau permintaan | Pemohon | `requests.create` | Permintaan `submitted` tersimpan dan pemohon dapat melihatnya. |
| UC-09 | Putuskan permintaan | Petugas stok | `requests.approve` | Permintaan menjadi `approved` atau `rejected`. |
| UC-10 | Penuhi permintaan | Petugas stok | `requests.fulfill` | Permintaan `fulfilled`, saldo berkurang, mutasi tercatat. |
| UC-11 | Catat mutasi stok | Petugas stok | `stock.adjust` | Saldo berubah dan riwayat mutasi bertambah. |
| UC-12 | Scan untuk menemukan barang/tindakan | Pengguna berizin | Salah satu izin halaman scan; izin aksi terpisah | Item dan transaksi yang dapat diproses tampil berdasarkan SKU. |
| UC-13 | Atur pengguna, role, dan izin | Administrator | `access.manage` | Hak akses akun diperbarui. |

## Rincian use case utama

### UC-03 — Kelola jenis dan lokasi

- **Prasyarat:** Administrator telah masuk dan memiliki `locations.manage`.
- **Alur utama:** buka **Lokasi & jenis** → tambah jenis bila diperlukan → masukkan nama lokasi yang unik, pilih jenis serta alur → simpan. Untuk pembaruan, buka lokasi yang ada, ubah nama/jenis/status, lalu simpan.
- **Alternatif:** nama jenis/lokasi ganda ditolak. Perubahan alur ditolak jika lokasi sudah mempunyai item.
- **Hasil:** lokasi baru aktif dan dapat dipakai mendaftarkan barang. Lokasi nonaktif tetap menyimpan riwayat lama, tetapi tidak tersedia untuk barang atau pengajuan baru.

### UC-04 — Kelola katalog dan label

- **Prasyarat:** pengguna memiliki `items.manage`; lokasi aktif sudah ada.
- **Alur utama:** pilih lokasi → **Tambah barang** → isi SKU unik, nama, satuan, jumlah total untuk alur pinjaman, batas minimum → simpan. Pilih **Label** → **Cetak label** untuk barcode.
- **Alternatif:** SKU ganda atau format SKU tidak sah ditolak. Barang alur stok dibuat dengan saldo 0, lalu stok awal dicatat lewat UC-11. Lokasi item tidak dapat dipindahkan lewat form edit.
- **Hasil:** item dapat dicari dan dipindai berdasarkan SKU.

### UC-05 sampai UC-07 — Pinjaman alat

- **Prasyarat:** item dan lokasi alur `loan` aktif; pemohon memiliki `loans.create`.
- **Alur utama:** pemohon memilih alat, jumlah, tanggal mulai, tanggal kembali, dan keperluan → sistem menyimpan `submitted` → petugas memilih **Setujui** → petugas menyerahkan alat dan memilih **Catat alat diserahkan** atau **Serahkan** setelah scan → sistem menyimpan `issued` → ketika alat kembali, petugas memilih **Catat pengembalian** atau **Terima kembali** → sistem menyimpan `returned`.
- **Alternatif:** petugas dapat **Tolak** saat `submitted` dengan alasan wajib, menghasilkan `rejected`. Serah terima ditolak bila jumlah tersedia kurang. Tanggal mulai tidak boleh sebelum hari ini dan tanggal kembali tidak boleh sebelum tanggal mulai. Aksi di luar urutan status ditolak.
- **Hasil:** `issued` mengurangi nilai tersedia secara terhitung; `returned` membuat jumlah tersebut tersedia kembali. Total `items.quantity` tidak berubah.

### UC-08 sampai UC-10 — Permintaan barang

- **Prasyarat:** item dan lokasi alur `stock` aktif; pemohon memiliki `requests.create`.
- **Alur utama:** pemohon memilih barang, jumlah, dan keperluan → sistem menyimpan `submitted` → petugas **Setujui** → petugas **Catat barang dikeluarkan** atau **Keluarkan barang** setelah scan → sistem mengecek stok, mengurangi saldo, mencatat `StockMovement`, dan mengubah permintaan menjadi `fulfilled`.
- **Alternatif:** petugas dapat **Tolak** saat `submitted` dengan alasan wajib. Jika stok kurang saat pemenuhan, status tetap `approved` dan saldo tidak berubah. Aksi berulang atau melompati urutan status ditolak.
- **Hasil:** saldo dan mutasi menggambarkan barang yang benar-benar dikeluarkan. Persetujuan sendiri tidak mengurangi stok.

### UC-11 dan UC-12 — Mutasi dan pemindaian

- **Prasyarat:** pengguna sudah masuk. Untuk mutasi stok perlu `stock.adjust`; untuk serah terima atau pemenuhan perlu izin aksi masing-masing.
- **Alur utama:** pengguna membuka **Scan barang** → scanner keyboard mengisi SKU dan Enter, atau kamera HP membaca Code 128/QR → sistem mencari item berdasarkan SKU → pengguna memilih aksi yang tersedia. Untuk stok langsung, pilih arah masuk/keluar, jumlah, dan alasan → sistem menyimpan saldo serta mutasi.
- **Alternatif:** SKU tidak ditemukan; kamera tidak diizinkan/tidak tersedia; saldo akan negatif; transaksi yang diharapkan belum disetujui; pengguna tidak punya izin. Sistem menampilkan pesan atau menolak aksi sesuai kondisi.
- **Hasil:** scan sendiri tidak mengubah data. Perubahan terjadi hanya setelah pengguna mengirim tindakan POST yang diizinkan.

### UC-13 — Atur akses

- **Prasyarat:** pengguna memiliki `access.manage`.
- **Alur utama:** buka **Role & akses** → buat/ubah role dan centang izin yang ada → buat/ubah pengguna dan tetapkan satu atau lebih role → simpan.
- **Alternatif:** nama role atau email ganda ditolak; Administrator tidak dapat diubah melalui form role; Administrator tidak dapat melepas role Administrator dari akunnya sendiri.
- **Hasil:** middleware dan tampilan memakai izin terbaru pada permintaan berikutnya.

## Aturan lintas use case

Semua proses bisnis memerlukan login. Daftar/detail pengajuan dibatasi pada data sendiri kecuali akun memiliki `loans.view-all` atau `requests.view-all`. Aksi tulis dibatasi 30 kali per menit per pengguna; login 5 kali per menit per email+IP; pencarian scan 120 kali per menit per pengguna. Izin saat ini berlaku lintas lokasi, bukan khusus Gudang A atau Gudang B.
