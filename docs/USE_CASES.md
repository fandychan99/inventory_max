# Use case TanggapEquip

## Ruang lingkup dan aktor

Sistem mengelola banyak lokasi. Setiap lokasi memilih salah satu alur: **pinjam kembali** untuk alat, **permintaan stok** untuk barang yang saldonya berkurang saat dikeluarkan, atau **pengecekan** untuk daftar peralatan tetap yang diperiksa berkala. Gudang A/B adalah data awal, bukan batas jumlah lokasi. Scanner USB/Bluetooth, kamera laptop/HP, dan gambar barcode adalah cara memasukkan SKU atau satu barcode lokasi; keputusan transaksi tetap dibuat pengguna yang telah masuk.

| Aktor | Tanggung jawab |
| --- | --- |
| Pemohon | Melihat katalog, mengajukan pinjaman atau permintaan, memantau pengajuan sendiri. |
| Petugas alur pinjaman | Menilai pengajuan, menyerahkan, dan menerima kembali alat. Role awal: Petugas Gudang A. |
| Petugas alur stok | Menilai permintaan, mencatat stok dan pengeluaran barang, serta mengunduh data stok. Role awal: Petugas Gudang B. |
| Petugas pemeriksaan | Memindai barcode lokasi dan mencatat jumlah serta kondisi semua peralatan. Role awal: Petugas Pemeriksaan. |
| Administrator | Menyiapkan lokasi, barang, pengguna, role, izin, dan melakukan semua tindakan operasional. |

Role di tabel adalah penugasan awal. Sistem memutuskan hak dari **permission**, sehingga Administrator bisa membuat kombinasi role lain.

## Daftar use case

| ID | Use case | Aktor utama | Izin utama | Hasil |
| --- | --- | --- | --- | --- |
| UC-01 | Masuk/keluar | Semua pengguna | Akun terdaftar | Sesi autentikasi dibuat atau diakhiri. |
| UC-02 | Pantau ringkasan | Semua role bawaan | `dashboard.view` | Statistik dan aktivitas terbaru terlihat sesuai cakupan akses transaksi. |
| UC-03 | Kelola jenis dan lokasi | Administrator | `locations.manage` | Jenis/lokasi bertambah atau berubah. |
| UC-04 | Kelola master, penempatan, dan label | Administrator atau role khusus | `items.manage`; label: `items.view` | Identitas barang dibuat sekali, lalu ditempatkan di satu atau banyak lokasi. |
| UC-05 | Ajukan dan pantau pinjaman | Pemohon | `loans.create` | Pinjaman `submitted` tersimpan dan pemohon dapat melihatnya. |
| UC-06 | Putuskan pinjaman | Petugas pinjaman | `loans.approve` | Pinjaman menjadi `approved` atau `rejected`. |
| UC-07 | Serahkan dan terima kembali alat | Petugas pinjaman | `loans.handover` | Pinjaman menjadi `issued`, lalu `returned`; ketersediaan berubah. |
| UC-08 | Ajukan dan pantau permintaan | Pemohon | `requests.create` | Permintaan `submitted` tersimpan dan pemohon dapat melihatnya. |
| UC-09 | Putuskan permintaan | Petugas stok | `requests.approve` | Permintaan menjadi `approved` atau `rejected`. |
| UC-10 | Penuhi permintaan | Petugas stok | `requests.fulfill` | Permintaan `fulfilled`, saldo berkurang, mutasi tercatat. |
| UC-11 | Catat mutasi stok | Petugas stok | `stock.adjust` | Saldo berubah dan riwayat mutasi bertambah. |
| UC-12 | Scan untuk menemukan barang/lokasi | Pengguna berizin | Salah satu izin halaman scan; izin aksi terpisah | Item atau checklist lokasi tampil berdasarkan kode yang dipindai. |
| UC-13 | Atur pengguna, role, dan izin | Administrator | `access.manage` | Hak akses akun diperbarui. |
| UC-14 | Catat dan tinjau pengecekan | Petugas pemeriksaan | `checks.perform`; lihat: `checks.view` | Snapshot semua peralatan di lokasi tersimpan dengan status sesuai/perlu tindak lanjut. |
| UC-15 | Cetak barcode lokasi | Petugas pemeriksaan atau Administrator | `checks.view` | Satu label Code 128 mewakili satu truk/lemari. |
| UC-16 | Unduh katalog lokasi ke Excel | Petugas operasional atau Administrator | `items.view`, `stock.export` | XLSX untuk satu lokasi terunduh dengan kolom sesuai alurnya. |
| UC-17 | Arsipkan/pulihkan lokasi dan jenis | Administrator | `locations.manage` | Entri hilang dari katalog/pilihan tanpa menghapus transaksi lama. |
| UC-18 | Hapus/arsipkan penempatan lokasi | Pengelola barang | `items.manage` | Barang hilang dari satu katalog, master dan lokasi lain tetap. |
| UC-19 | Hapus/arsipkan master seluruh lokasi | Administrator | `items.delete-master` | Master dan semua penempatannya hilang dari katalog aktif. |

## Rincian use case utama

### UC-03 — Kelola jenis dan lokasi

- **Prasyarat:** Administrator telah masuk dan memiliki `locations.manage`.
- **Alur utama:** buka **Lokasi & jenis** → tambah jenis bila diperlukan → masukkan nama lokasi yang unik, pilih jenis serta alur → untuk alur pengecekan isi kode scan unik → simpan. Untuk pembaruan, buka lokasi yang ada, ubah nama/jenis/status, lalu simpan. Bila tidak dipakai, arsipkan lokasi dan kemudian jenisnya; pulihkan jenis sebelum lokasi.
- **Alternatif:** nama jenis/lokasi ganda ditolak. Perubahan alur ditolak jika lokasi sudah mempunyai item.
- **Hasil:** lokasi baru aktif dan dapat dipakai mendaftarkan barang. Lokasi yang diarsipkan tidak muncul di katalog/scan/pilihan operasional, tetapi barang serta transaksi lama tetap tersimpan. Jenis dengan lokasi yang masih terlihat tidak dapat diarsipkan.

### UC-04 — Kelola master, penempatan, dan label

- **Prasyarat:** pengguna memiliki `items.manage`; lokasi aktif diperlukan saat menempatkan master.
- **Alur utama:** buka **Master barang** → buat satu SKU unik, nama, dan satuan → buka katalog lokasi → **Tempatkan barang** → pilih master, lalu isi jumlah total/standar atau stok awal sesuai alur. Ulangi pada lokasi lain dengan master yang sama. Stok awal positif dicatat sebagai mutasi. Pilih **Label** → **Cetak label** untuk barcode barang.
- **Alternatif:** SKU master ganda atau format tidak sah ditolak. Master yang sama tidak boleh ditempatkan dua kali di lokasi yang sama. Pengguna tanpa `stock.adjust` hanya dapat menempatkan barang stok dengan saldo 0, lalu petugas berizin mencatat stok awal lewat UC-11. Lokasi penempatan tidak dapat dipindahkan lewat form edit.
- **Hasil:** item dapat dicari dan dipindai berdasarkan SKU; jumlah dan status setiap lokasi tetap independen.

### UC-05 sampai UC-07 — Pinjaman alat

- **Prasyarat:** item dan lokasi alur `loan` aktif; pemohon memiliki `loans.create`.
- **Alur utama:** pemohon memilih alat, jumlah, tanggal mulai, tanggal kembali, dan keperluan → sistem menyimpan `submitted` → petugas memilih **Setujui** → petugas menyerahkan alat dan memilih **Catat alat diserahkan** atau **Serahkan** setelah scan → sistem menyimpan `issued` → ketika alat kembali, petugas memilih **Catat pengembalian** atau **Terima kembali** → sistem menyimpan `returned`.
- **Alternatif:** petugas dapat **Tolak** saat `submitted` dengan alasan wajib, menghasilkan `rejected`. Serah terima ditolak bila jumlah tersedia kurang. Tanggal mulai tidak boleh sebelum hari ini dan tanggal kembali tidak boleh sebelum tanggal mulai. Aksi di luar urutan status ditolak.
- **Hasil:** `issued` mengurangi nilai tersedia secara terhitung; `returned` membuat jumlah tersebut tersedia kembali. Total `items.quantity` tidak berubah.

### UC-08 sampai UC-10 — Permintaan barang

- **Prasyarat:** item dan lokasi alur `stock` aktif; pemohon memiliki `requests.create`.
- **Alur utama:** pemohon memilih barang, jumlah, dan keperluan → sistem menyimpan `submitted` → petugas **Setujui** → petugas **Catat barang dikeluarkan** atau **Keluarkan barang** setelah scan → sistem mengecek stok, mengurangi saldo, mencatat `StockMovement`, dan mengubah permintaan menjadi `fulfilled`.
- **Alternatif:** petugas dapat **Tolak** saat `submitted` dengan alasan wajib. Jika stok kurang saat pemenuhan, status tetap `approved` dan saldo tidak berubah. Aksi berulang atau melompati urutan status ditolak.
- **Hasil:** saldo dan mutasi menggambarkan barang yang benar-benar dikeluarkan. Persetujuan sendiri tidak mengurangi stok. Koreksi saldo melalui form edit barang memerlukan izin `stock.adjust` dan alasan; selisihnya juga dicatat sebagai mutasi.

### UC-16 — Unduh katalog lokasi

- **Prasyarat:** pengguna memiliki `items.view` dan `stock.export`.
- **Alur utama:** buka katalog lokasi pinjam, stok, atau pengecekan → pilih **Excel lokasi ini** → sistem mengambil item dari lokasi tersebut dan menghasilkan `.xlsx` berisi identitas barang serta jumlah dan status sesuai alur.
- **Alternatif:** lokasi tidak dipilih atau sudah diarsipkan, atau pengguna tidak punya izin; sistem menolak unduhan. Filter pencarian pada katalog berlaku untuk ekspor lokasi itu.
- **Hasil:** berkas Excel dapat dibuka tanpa mengubah saldo. Nilai teks disimpan sebagai teks agar nama/SKU yang menyerupai rumus tidak dijalankan.

### UC-17 — Arsipkan dan pulihkan lokasi/jenis

- **Prasyarat:** Administrator memiliki `locations.manage`.
- **Alur utama:** pada **Lokasi & jenis**, pilih **Arsipkan lokasi** → sistem menandai lokasi diarsipkan dan nonaktif → lokasi hilang dari katalog dan pilihan operasional. Setelah semua lokasi suatu jenis diarsipkan, pilih **Arsipkan jenis**. Gunakan bagian arsip untuk memulihkan jenis, kemudian lokasi.
- **Alternatif:** jenis masih mempunyai lokasi yang terlihat, atau lokasi dipulihkan ketika jenisnya masih diarsipkan; sistem menolak dan menjelaskan urutannya.
- **Hasil:** data barang dan transaksi lama tetap tersimpan; transaksi berjalan masih dapat diselesaikan dari detailnya. Lokasi yang dipulihkan masih nonaktif sampai diaktifkan secara sengaja.

### UC-18 — Hapus penempatan pada satu lokasi

- **Prasyarat:** pengguna memiliki `items.manage` dan barang masih ada dalam katalog lokasi tersebut.
- **Alur utama:** pilih **Hapus dari lokasi** pada baris barang → konfirmasi → sistem memeriksa saldo/jumlah alat dan transaksi berjalan → penempatan hilang dari katalog lokasi ini. Master dan penempatan di lokasi lain tidak berubah.
- **Alternatif:** jika ada riwayat, penempatan masuk **Arsip barang** dan dapat dipulihkan; tanpa riwayat, penempatan dihapus permanen. Saldo/jumlah alat positif pada alur pinjam atau stok, maupun pengajuan yang belum selesai, menolak seluruh tindakan.
- **Hasil:** katalog, scan, dan ekspor hanya menampilkan penempatan aktif; halaman transaksi lama tetap menunjukkan barang yang diarsipkan.

### UC-19 — Hapus master dari semua lokasi

- **Prasyarat:** pengguna memiliki `items.delete-master` (Administrator bawaan).
- **Alur utama:** buka **Master barang** → pilih **Hapus master** → konfirmasi → sistem memeriksa semua penempatan dalam satu transaksi dan menghilangkan semuanya dari katalog aktif.
- **Alternatif:** jika ada saldo atau transaksi berjalan pada salah satu lokasi, tidak ada data yang dihapus. Jika ada riwayat, master dan penempatan terkait diarsipkan; bila semua tanpa riwayat, data dihapus permanen.
- **Hasil:** master hilang dari pilihan penempatan baru. Master bersejarah dapat dipulihkan melalui **Arsip master**, lalu penempatannya melalui **Arsip barang**.

### UC-11 dan UC-12 — Mutasi dan pemindaian

- **Prasyarat:** pengguna sudah masuk. Untuk mutasi stok perlu `stock.adjust`; untuk serah terima atau pemenuhan perlu izin aksi masing-masing.
- **Alur utama:** pengguna membuka **Scan barang** → scanner keyboard mengisi kode dan Enter, kamera laptop/HP membaca Code 128/QR, atau pengguna memilih gambar barcode dari perangkat → sistem mencari barcode lokasi checklist atau semua penempatan item berdasarkan SKU → bila beberapa lokasi memakai SKU yang sama, pengguna memilih lokasi → pengguna memilih aksi yang tersedia. Untuk stok langsung, pilih arah masuk/keluar, jumlah, dan alasan → sistem menyimpan saldo serta mutasi. Barcode lokasi membuka UC-14.
- **Alternatif:** SKU tidak ditemukan; kamera tidak diizinkan/tidak tersedia; gambar tidak memuat kode yang terbaca; saldo akan negatif; transaksi yang diharapkan belum disetujui; pengguna tidak punya izin. Sistem menampilkan pesan atau menolak aksi sesuai kondisi. Gambar diproses di browser dan tidak disimpan di server.
- **Hasil:** scan sendiri tidak mengubah data. Perubahan terjadi hanya setelah pengguna mengirim tindakan POST yang diizinkan.

### UC-14 dan UC-15 — Pengecekan peralatan lokasi

- **Prasyarat:** lokasi alur `checklist` aktif, mempunyai `scan_code` unik dan minimal satu item aktif; petugas memiliki `checks.perform` untuk mencatat.
- **Alur utama:** cetak barcode lokasi → tempel pada truk/lemari → petugas scan satu barcode → sistem menampilkan seluruh peralatan aktif dan jumlah standar → petugas mengisi jumlah ditemukan serta kondisi tiap baris → simpan → sistem menyimpan `Inspection` dan snapshot `InspectionEntry` per alat.
- **Alternatif:** daftar item berubah saat form terbuka; ada baris yang tidak dikirim; selisih jumlah atau kerusakan tanpa catatan; lokasi nonaktif; pengguna tidak punya izin. Sistem menolak penyimpanan. Pemeriksaan ulang pada hari yang sama membuat catatan baru.
- **Hasil:** status `ok` bila semua sesuai, `attention` bila ada selisih/kerusakan. Riwayat, petugas, waktu, dan rincian tersedia; jumlah standar dan saldo stok tidak berubah otomatis.

### UC-13 — Atur akses

- **Prasyarat:** pengguna memiliki `access.manage`.
- **Alur utama:** buka **Role & akses** → buat/ubah role dan centang izin yang ada → buat/ubah pengguna dan tetapkan satu atau lebih role → simpan.
- **Alternatif:** nama role atau email ganda ditolak; Administrator tidak dapat diubah melalui form role; Administrator tidak dapat melepas role Administrator dari akunnya sendiri.
- **Hasil:** middleware dan tampilan memakai izin terbaru pada permintaan berikutnya.

## Aturan lintas use case

Semua proses bisnis memerlukan login. Daftar/detail pengajuan dibatasi pada data sendiri kecuali akun memiliki `loans.view-all` atau `requests.view-all`. Aksi tulis dibatasi 30 kali per menit per pengguna; login 5 kali per menit per email+IP; pencarian scan 120 kali per menit per pengguna. Izin saat ini berlaku lintas lokasi, bukan khusus Gudang A atau Gudang B.
