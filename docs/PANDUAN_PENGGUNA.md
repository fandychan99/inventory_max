# Panduan penggunaan TanggapEquip

Panduan ini mengikuti tampilan dan alur aplikasi saat ini. Menu yang terlihat bergantung pada izin akun. Istilah **lokasi** mencakup gudang, truk, lemari, atau tempat penyimpanan lain.

## 1. Masuk dan mengenali menu

1. Buka alamat aplikasi yang diberikan pengelola, lalu masuk dengan email dan kata sandi akun Anda. Akun awal Administrator dibuat saat instalasi dari `INITIAL_ADMIN_EMAIL` dan `INITIAL_ADMIN_PASSWORD`; tidak ada kata sandi umum yang dibagikan aplikasi.
2. Setelah masuk, halaman **Ringkasan** menampilkan pinjaman berjalan, keterlambatan, permintaan menunggu, stok menipis, progres pengecekan harian, serta kartu per lokasi.
3. Gunakan menu **Scan barang**, **Master barang**, **Pengecekan**, nama lokasi di **Lokasi · Katalog**, **Peminjaman**, **Permintaan**, **Lokasi & jenis**, atau **Role & akses** sesuai tugas Anda. Klik **Keluar** setelah selesai, terutama pada perangkat bersama.

| Role bawaan | Kegiatan utama |
| --- | --- |
| Administrator | Semua menu, pengaturan lokasi, barang, transaksi, akun, role, dan izin. |
| Petugas Gudang A | Melihat barang, mengajukan/memantau seluruh peminjaman, menyetujui, menyerahkan, dan menerima kembali alat. |
| Petugas Gudang B | Melihat barang, menyesuaikan dan mengunduh stok, mengajukan/memantau seluruh permintaan, menyetujui, dan mengeluarkan barang. |
| Petugas Pemeriksaan | Melihat lokasi checklist dan mencatat pemeriksaan peralatan harian. |
| Pemohon | Melihat barang dan ringkasan, mengajukan pinjaman atau permintaan, serta melihat pengajuan sendiri. |

Nama role bawaan merupakan contoh penugasan. Administrator dapat membuat role lain dan mengubah izinnya. Izin berlaku untuk semua lokasi dengan alur terkait; aplikasi saat ini belum membatasi petugas ke satu lokasi tertentu.

## 2. Menyiapkan jenis dan lokasi (Administrator)

1. Buka **Lokasi & jenis**. Di **Tambah jenis lokasi**, masukkan nama seperti `Truk` atau `Lemari`, lalu pilih **Tambah jenis**. Jenis yang ada dapat diganti namanya di **Jenis tersedia**.
2. Di **Tambah lokasi**, isi nama unik, pilih jenis, lalu pilih **Alur barang**:
   - **Pinjam kembali**: alat diserahkan lalu dikembalikan. Contoh awal: Gudang A.
   - **Permintaan stok**: barang dikeluarkan dan saldo berkurang. Contoh awal: Gudang B.
   - **Pengecekan**: peralatan tetap terdaftar di satu lokasi dan diperiksa berkala. Contoh: truk patroli.
3. Untuk alur **Pengecekan**, isi **Kode scan lokasi** yang unik. Satu barcode ini mewakili seluruh peralatan di truk/lemari. Pilih **Tambah lokasi**. Jumlah lokasi tidak dibatasi dua.
4. Untuk mengubah nama, jenis, atau status aktif, buka baris lokasi di **Daftar lokasi**, ubah datanya, lalu pilih **Simpan lokasi**. Lokasi yang sudah mempunyai barang tidak dapat berganti alur. Menonaktifkan lokasi mencegah penambahan barang dan pengajuan baru, sementara data lama tetap bisa ditinjau.
5. Untuk menghilangkan lokasi dari katalog, pilih **Arsipkan lokasi** pada barisnya. Lokasi akan hilang dari menu katalog, kartu lokasi di ringkasan, scan, dan pilihan pengajuan baru; barang serta riwayatnya tetap tersimpan. Aktivitas transaksi lama masih dapat menampilkan nama lokasi dan diselesaikan melalui halaman detailnya. Pulihkan melalui **Lokasi diarsipkan**, lalu aktifkan kembali bila siap digunakan.
6. Untuk menghilangkan jenis lokasi dari pilihan, arsipkan semua lokasi di dalamnya lebih dulu, lalu pilih **Arsipkan jenis**. Jenis dapat dipulihkan melalui **Jenis diarsipkan**. Nama lokasi dan jenis yang diarsipkan tetap tersimpan sehingga tidak dapat langsung dipakai ulang.

## 3. Mendaftarkan barang dan mencetak label

1. Buka **Master barang** → **Tambah master**. Isi **Kode barang (SKU)**, nama, satuan, dan keterangan bila perlu. SKU unik di master dan boleh berisi huruf, angka, titik, garis bawah, garis miring, atau tanda hubung.
2. Buka nama lokasi di katalog → **Tempatkan barang** → pilih master barang. Master yang sama dapat dipilih di Fire Truck A dan B maupun Gudang A dan B. Satu master hanya boleh ditempatkan sekali pada lokasi yang sama.
3. Untuk lokasi **pinjam kembali**, isi **Jumlah total alat**. Untuk lokasi **pengecekan**, isi **Jumlah standar** yang seharusnya ada di lokasi. Untuk lokasi **permintaan stok**, pengguna yang memiliki izin `items.manage` dan `stock.adjust` dapat mengisi **Stok awal** saat menempatkan barang. Isi **Catatan stok awal** bila perlu; jika kosong, mutasi diberi catatan “Stok awal”. Pengguna tanpa izin `stock.adjust` dapat menempatkan barang dengan stok awal 0.
4. Pilih **Tempatkan barang**. Di daftar lokasi, pilih **Ubah** untuk memperbarui jumlah, batas minimum, atau status aktif. Ubah kode, nama, satuan, dan keterangan melalui **Master barang**; perubahan berlaku di semua lokasi. Lokasi barang tidak bisa dipindah lewat formulir ini.
5. Pilih **Label**, lalu **Cetak label**. Tempel barcode Code 128 pada barang atau rak. Untuk lokasi alur pengecekan, cetak **Barcode lokasi** dari menu **Pengecekan**, lalu tempel satu label pada truk/lemari.

Pilih **Hapus dari lokasi** untuk melepas satu penempatan tanpa mengubah master atau lokasi lain. Administrator dapat memilih **Hapus master** untuk semua lokasi sekaligus (izin `items.delete-master`). Jumlah alat/stok harus 0 dan transaksi berjalan harus selesai. Data tanpa riwayat dihapus permanen; data bersejarah masuk **Arsip barang** atau **Arsip master** agar transaksi lama tetap terbaca. Pulihkan master sebelum memulihkan penempatannya.

**Tersedia** pada alat pinjam kembali = total alat dikurangi jumlah yang berstatus **Dipinjam**. Pada barang stok, **Tersedia** sama dengan saldo saat ini. Pengajuan yang baru disetujui belum mengurangi ketersediaan; pemeriksaan ulang dilakukan ketika alat diserahkan atau barang dikeluarkan.

## 4. Mencatat stok masuk, keluar, dan koreksi

Untuk barang di lokasi **permintaan stok**, buka katalog dan pilih **Mutasi** pada barang terkait. Isi **Perubahan jumlah** dengan angka positif untuk menambah atau negatif untuk mengurangi, tulis **Alasan penyesuaian**, lalu **Simpan mutasi**. Riwayat menampilkan waktu, jenis, perubahan, saldo akhir, petugas, dan catatan.

Alternatifnya, buka **Scan barang**, baca SKU, pilih lokasi bila kode ada di beberapa tempat, lalu pada **Stok masuk / keluar langsung** pilih **Masuk** atau **Keluar**, isi jumlah dan alasan/nomor dokumen, dan pilih **Catat mutasi stok**. Untuk memenuhi permintaan yang sudah disetujui, gunakan tombol **Keluarkan barang** pada daftar permintaan, agar pengeluaran terhubung ke nomor permintaan. Saldo tidak boleh menjadi negatif.

Administrator atau akun dengan izin mengelola barang sekaligus menyesuaikan stok dapat membuka **Ubah** pada penempatan barang, mengubah **Stok saat ini**, lalu mengisi **Alasan perubahan stok**. Selisih angka dicatat otomatis sebagai mutasi. Saat menempatkan barang baru, akun dengan kedua izin tersebut juga dapat langsung mengisi **Stok awal**; jumlahnya dicatat sebagai mutasi. Barang yang ditempatkan dengan stok awal 0 dapat diisi kemudian melalui **Mutasi**.

Untuk mengunduh data, buka katalog lokasi mana pun lalu pilih satu tombol **Excel lokasi ini**. Berkas `.xlsx` hanya berisi barang pada lokasi yang sedang dibuka dan mengikuti pencarian nama/SKU. Gudang pinjam menampilkan jumlah total dan tersedia; lokasi permintaan stok menampilkan saldo, batas minimum, dan status stok; lokasi pengecekan menampilkan jumlah standar. Ekspor tersedia untuk Administrator serta petugas operasional bawaan, dan izin `stock.export` dapat diatur dari **Role & akses**.

## 5. Alur peminjaman alat

1. Pemohon membuka **Peminjaman** → **Ajukan pinjam**. Pilih alat, jumlah, tanggal mulai pakai, rencana kembali, dan keperluan; pilih **Kirim pengajuan**. Satu pengajuan berisi satu jenis alat.
2. Petugas dengan izin persetujuan membuka **Peminjaman** → **Detail**. Pada status **Diajukan**, pilih **Setujui** atau tulis alasan lalu pilih **Tolak**.
3. Pada status **Disetujui**, petugas dengan izin serah terima memilih **Catat alat diserahkan** di halaman detail, atau scan label dan pilih **Serahkan** pada pengajuan yang sesuai. Sistem mengecek kembali unit tersedia saat penyerahan.
4. Saat alat kembali, petugas membuka detail atau scan lagi, mengisi kondisi/catatan bila ada, kemudian memilih **Catat pengembalian** atau **Terima kembali**. Status menjadi **Dikembalikan** dan unit kembali tersedia.

Daftar **Peminjaman** dapat difilter berdasarkan lokasi dan status. Pengguna tanpa izin melihat semua pengajuan hanya melihat miliknya. Ringkasan **Terlambat kembali** menghitung pinjaman berstatus **Dipinjam** yang melewati tanggal rencana kembali; aplikasi belum melakukan penagihan otomatis.

## 6. Alur permintaan barang stok

1. Pemohon membuka **Permintaan** → **Ajukan permintaan**. Pilih barang, jumlah, dan keperluan; pilih **Kirim permintaan**. Satu permintaan berisi satu jenis barang.
2. Petugas dengan izin persetujuan membuka **Detail**. Pada status **Diajukan**, pilih **Setujui** atau isi alasan lalu **Tolak**.
3. Pada status **Disetujui**, petugas dengan izin pemenuhan memilih **Catat barang dikeluarkan** di detail, atau scan SKU lalu pilih **Keluarkan barang** pada permintaan yang sesuai.
4. Saat barang dikeluarkan, saldo stok berkurang dan mutasi terkait permintaan tercatat. Jika stok kurang, pemenuhan ditolak; tambah stok melalui mutasi lalu ulangi pemenuhan.

Persetujuan sendiri belum mengurangi stok. Daftar **Permintaan** dapat difilter berdasarkan lokasi dan status; pemohon biasa hanya melihat miliknya.

## 7. Pengecekan peralatan truk/lemari

1. Administrator membuat lokasi beralur **Pengecekan**, menetapkan kode scan lokasi, lalu mendaftarkan semua peralatan beserta jumlah standarnya. Cetak satu barcode lokasi dan pasang pada truk/lemari.
2. Petugas membuka **Scan barang**, memindai barcode lokasi dengan scanner atau kamera HP. Aplikasi membuka daftar semua peralatan aktif di lokasi itu. Daftar yang sama dapat dibuka dari menu **Pengecekan**.
3. Periksa setiap baris, isi **Ditemukan** dan **Kondisi**. Jika jumlah berbeda dari standar atau ada alat rusak, isi catatan pada baris tersebut. Tambahkan catatan umum bila perlu, lalu pilih **Simpan hasil pengecekan**.
4. Hasil tercatat dengan tanggal, jam, nama petugas, rincian peralatan, dan status **Sesuai** atau **Perlu tindak lanjut**. Riwayat dapat dilihat pada halaman lokasi dan detail pemeriksaan. Pemeriksaan boleh diulang pada hari yang sama; setiap kiriman menjadi catatan tersendiri.

Pengecekan tidak otomatis mengubah jumlah standar, stok, atau transaksi pinjam. Bila ada selisih, tindak lanjuti secara operasional dan ubah jumlah standar pada penempatan lokasi bila memang berubah.

## 8. Menggunakan scanner, kamera, dan gambar barcode

1. Buka **Scan barang**. Dengan scanner USB/Bluetooth yang bertindak sebagai keyboard, fokuskan kolom **Kode pada label** lalu pindai barcode. Atur scanner agar mengirim **Enter**; jika tidak, pilih **Cari**.
2. Pada laptop atau HP, pilih **Buka kamera** dan izinkan akses. Laptop memakai kamera depan, HP memakai kamera belakang bila tersedia. Jika perangkat memiliki beberapa kamera, pilih sumber lain pada daftar kamera. Arahkan ke barcode Code 128 atau QR berisi SKU/kode lokasi; pilih **Tutup kamera** setelah selesai.
3. Sebagai alternatif, pilih **Unggah gambar barcode** lalu ambil foto/screenshot PNG, JPG, WebP, atau GIF (maksimal 10 MB). Gambar dibaca di browser tanpa dikirim ke server. Jika gagal terbaca, gunakan gambar tajam dengan kode utuh. Kode yang terbaca membuka hasil yang sama seperti scan kamera.
4. Jika satu SKU berada di beberapa lokasi, pilih lokasi yang tepat. Barcode lokasi checklist langsung membuka daftar peralatan. Pilih tindakan yang tersedia sesuai alur dan izin: **Serahkan/Terima kembali**, **Keluarkan barang**, mutasi stok, atau **Simpan hasil pengecekan**.

`http://127.0.0.1:8000` hanya menunjuk komputer tempat server berjalan. HP perlu alamat server yang dapat dijangkau dan HTTPS agar browser memberi akses kamera; pada laptop, `localhost` atau `127.0.0.1` juga dapat memakai kamera. Jika kamera gagal, periksa izin browser dan privasi perangkat, kamera yang dipakai aplikasi lain, serta apakah webcam terdeteksi. Unggah gambar dan scanner USB/Bluetooth tetap dapat digunakan tanpa kamera.

## 9. Mengelola akun dan hak akses (Administrator)

Di **Role & akses**, buat role dengan nama dan centang izin yang dibutuhkan. Buka role yang ada untuk mengubah nama atau izin, lalu **Simpan perubahan**. Role **Administrator** dilindungi dan memiliki semua izin.

Pada **Tambah pengguna**, isi nama, email, kata sandi beserta konfirmasi, dan role, lalu **Buat akun**. Untuk akun yang ada, buka baris pengguna, ubah data atau role, dan pilih **Simpan akun**. Biarkan kolom kata sandi kosong jika tidak mengubahnya. Administrator tidak dapat melepas role Administrator dari akunnya sendiri.

## 10. Pesan yang sering muncul

| Gejala | Arti dan langkah |
| --- | --- |
| `403` / menu tidak ada | Akun belum memiliki izin yang diperlukan. Minta Administrator memeriksa role. |
| `429 Too Many Requests` | Terlalu banyak percobaan dalam satu menit. Tunggu sebentar lalu ulangi. Login dibatasi 5/menit per email dan IP; aksi tulis 30/menit per pengguna; pencarian scan 120/menit per pengguna. |
| Alat tersedia/stok tidak mencukupi | Kuantitas dicek lagi pada saat serah terima atau pemenuhan. Periksa transaksi berjalan atau tambah stok. |
| Alur lokasi tidak dapat diubah | Lokasi sudah memiliki barang. Buat lokasi baru dengan alur yang diinginkan. |
| Kamera gagal dibuka | Gunakan HTTPS, izinkan kamera, tutup aplikasi lain yang memakai kamera, atau gunakan scanner keyboard. |

