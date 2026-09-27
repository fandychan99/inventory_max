# UML TanggapEquip

Diagram berikut menggambarkan implementasi saat ini. Kode status (`submitted`, `approved`, dan seterusnya) mengikuti nilai yang disimpan di database. Diagram Mermaid dapat dirender oleh penampil Markdown yang mendukung Mermaid. Sumber [diagram use case UML formal](uml/USE_CASE.puml) tersedia dalam PlantUML.

## 1. Diagram use case

```mermaid
flowchart LR
    applicant["Pemohon"]
    loanOfficer["Petugas alur pinjaman"]
    stockOfficer["Petugas alur stok"]
    admin["Administrator"]

    subgraph system["TanggapEquip"]
        login(["Masuk dan keluar"])
        dashboard(["Pantau ringkasan"])
        catalog(["Lihat katalog dan scan SKU"])
        applyLoan(["Ajukan pinjaman"])
        decideLoan(["Setujui atau tolak pinjaman"])
        handover(["Serahkan dan terima kembali alat"])
        applyStock(["Ajukan permintaan stok"])
        decideStock(["Setujui atau tolak permintaan"])
        fulfill(["Keluarkan barang permintaan"])
        adjust(["Catat mutasi stok"])
        locations(["Kelola jenis dan lokasi"])
        items(["Kelola barang dan label"])
        access(["Kelola pengguna, role, izin"])
    end

    applicant --> login & dashboard & catalog & applyLoan & applyStock
    loanOfficer --> login & dashboard & catalog & applyLoan & decideLoan & handover
    stockOfficer --> login & dashboard & catalog & applyStock & decideStock & fulfill & adjust
    admin --> login & dashboard & catalog & applyLoan & decideLoan & handover & applyStock & decideStock & fulfill & adjust & locations & items & access
```

Diagram ini menunjukkan hubungan aktor dan use case. Role dapat diubah melalui Spatie; garis di atas merepresentasikan izin bawaan, bukan izin yang harus tetap pada nama role tersebut.

## 2. Diagram kelas/domain

```mermaid
classDiagram
    class User {
        +id
        +name
        +email
    }
    class Role {
        +name
    }
    class Permission {
        +name
    }
    class LocationType {
        +name
    }
    class Location {
        +name
        +workflow: loan|stock
        +is_active
    }
    class Item {
        +sku
        +name
        +quantity
        +minimum_stock
        +available()
    }
    class Loan {
        +quantity
        +needed_from
        +due_on
        +status
    }
    class StockRequest {
        +quantity
        +status
    }
    class StockMovement {
        +change
        +balance_after
        +type
    }
    class InventoryWorkflow {
        +transitionLoan()
        +transitionRequest()
        +adjustStock()
    }

    User "many" -- "many" Role : assigned
    Role "many" -- "many" Permission : grants
    LocationType "1" --> "many" Location : groups
    Location "1" --> "many" Item : contains
    Item "1" --> "many" Loan : borrowedIn
    Item "1" --> "many" StockRequest : requestedIn
    Item "1" --> "many" StockMovement : has
    User "1" --> "many" Loan : requests
    User "1" --> "many" StockRequest : requests
    User "1" --> "many" StockMovement : records
    StockRequest "1" --> "0..1" StockMovement : source
    InventoryWorkflow ..> Loan : transitions
    InventoryWorkflow ..> StockRequest : transitions
    InventoryWorkflow ..> StockMovement : creates
```

Relasi pelaku persetujuan, penyerahan, pengembalian, dan pemenuhan juga menunjuk `User` melalui kolom `approved_by`, `issued_by`, `returned_by`, atau `fulfilled_by`; garisnya disederhanakan agar diagram terbaca.

## 3. Diagram status pinjaman

```mermaid
stateDiagram-v2
    [*] --> submitted: pengajuan dibuat
    submitted --> approved: approve
    submitted --> rejected: reject + alasan
    approved --> issued: issue + unit cukup
    issued --> returned: return
    rejected --> [*]
    returned --> [*]
```

`issued` mengurangi ketersediaan yang dihitung dari total alat. `returned` melepaskan jumlah itu kembali. Tidak ada perubahan `items.quantity` pada kedua langkah.

## 4. Diagram status permintaan stok

```mermaid
stateDiagram-v2
    [*] --> submitted: permintaan dibuat
    submitted --> approved: approve
    submitted --> rejected: reject + alasan
    approved --> fulfilled: fulfill + stok cukup
    rejected --> [*]
    fulfilled --> [*]
```

Saldo stok dan satu catatan mutasi `issued` dibuat saat `fulfilled`, bukan saat `approved`.

## 5. Diagram urutan penyerahan alat

```mermaid
sequenceDiagram
    actor Pemohon
    actor Petugas
    participant Web as Controller/Route
    participant WF as InventoryWorkflow
    participant DB as Database

    Pemohon->>Web: POST pengajuan pinjaman
    Web->>DB: Simpan Loan(submitted)
    Petugas->>Web: POST approve
    Web->>WF: transitionLoan(approve)
    WF->>DB: Kunci loan + item, ubah approved
    Petugas->>Web: Scan SKU, pilih Serahkan
    Web->>WF: transitionLoan(issue)
    WF->>DB: Kunci loan + item, hitung unit tersedia
    alt unit cukup
        WF->>DB: Ubah loan menjadi issued
        DB-->>Web: Berhasil
    else unit kurang
        WF-->>Web: Kesalahan validasi
    end
    Petugas->>Web: Pilih Terima kembali
    Web->>WF: transitionLoan(return)
    WF->>DB: Ubah loan menjadi returned
```

## 6. Diagram urutan pemenuhan permintaan stok

```mermaid
sequenceDiagram
    actor Pemohon
    actor Petugas
    participant Web as Controller/Route
    participant WF as InventoryWorkflow
    participant DB as Database

    Pemohon->>Web: POST permintaan barang
    Web->>DB: Simpan StockRequest(submitted)
    Petugas->>Web: POST approve
    Web->>WF: transitionRequest(approve)
    WF->>DB: Ubah status menjadi approved
    Petugas->>Web: Scan SKU, pilih Keluarkan barang
    Web->>WF: transitionRequest(fulfill)
    WF->>DB: Kunci request + item, cek saldo
    alt stok cukup
        WF->>DB: Kurangi quantity
        WF->>DB: Catat StockMovement(issued)
        WF->>DB: Ubah status menjadi fulfilled
        DB-->>Web: Berhasil
    else stok kurang
        WF-->>Web: Kesalahan validasi, transaksi dibatalkan
    end
```

## 7. Diagram aktivitas scan

```mermaid
flowchart TD
    start([Buka Scan barang]) --> source{Sumber kode}
    source -->|Scanner USB/Bluetooth| input[Isi SKU lalu Enter atau Cari]
    source -->|Kamera HP HTTPS| camera[Buka kamera dan baca Code 128/QR]
    input --> lookup[Cari Item berdasarkan SKU]
    camera --> lookup
    lookup --> found{Item ditemukan?}
    found -->|Tidak| retry[Tampilkan kode tidak ditemukan]
    found -->|Ya| workflow{Alur lokasi}
    workflow -->|Pinjam kembali| loan[Perlihatkan pinjaman approved/issued]
    workflow -->|Permintaan stok| stock[Perlihatkan permintaan approved dan form mutasi]
    loan --> action[Pengguna memilih aksi sesuai izin]
    stock --> action
    action --> post[POST divalidasi server]
    post --> result([Status atau saldo diperbarui])
```

Scan adalah pencarian item. Tindakan pada hasil scan tetap mengikuti izin, urutan status, dan pemeriksaan saldo pada server. Rincian kondisi ada di [use case](USE_CASES.md) dan [panduan kode](PANDUAN_KODE.md).
