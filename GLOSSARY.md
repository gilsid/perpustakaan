# Sirkulasi Perpustakaan

Glosarium untuk alur sirkulasi perpustakaan: siapa yang meminjam, apa yang dipinjam, dan apa yang terjadi saat bahan kembali.

## Orang

**Anggota**:
Pemustaka terdaftar yang memenuhi syarat untuk meminjam bahan perpustakaan.
_Avoid_: User, member, patron

**Nomor anggota**:
Kode unik yang melekat pada anggota dan dipindai di meja sirkulasi.
_Avoid_: member_number, nomor kartu

**Kartu anggota**:
Media fisik yang mencetak nomor anggota untuk dibawa pemustaka.
_Avoid_: kartu identitas, ID card

**Keanggotaan**:
Masa berlaku hak pinjam seorang anggota.
_Avoid_: status akun

**Petugas**:
Orang yang menjalankan check-out dan check-in di meja sirkulasi.
_Avoid_: admin, pustakawan

## Bahan

**Judul buku**:
Rekaman katalog yang menggambarkan sebuah karya, terlepas dari jumlah eksemplarnya.
_Avoid_: buku (bila maksudnya unit fisik), judul

**Eksemplar**:
Satu unit fisik dari sebuah judul buku yang dapat dipinjamkan.
_Avoid_: copy, item, stok

**Barcode eksemplar**:
Kode unik pada eksemplar yang dipindai saat check-out dan check-in.
_Avoid_: kode buku, ISBN

## Alur

**Pinjaman**:
Hubungan antara anggota dan bahan yang dipinjam, baik digital maupun fisik, sampai bahan diterima kembali.
_Avoid_: transaksi, peminjaman (bila maksudnya catatan)

**Check-out**:
Penyerahan eksemplar kepada anggota oleh petugas di meja sirkulasi.
_Avoid_: meminjam (bila pelakunya petugas), pengeluaran

**Check-in**:
Penerimaan kembali eksemplar oleh petugas, termasuk pencatatan kondisinya.
_Avoid_: pengembalian (bila pelakunya petugas), return

**Hold**:
Permintaan anggota untuk eksemplar berikutnya dari sebuah judul yang sedang habis.
_Avoid_: reservasi, pesanan, booking

## Tagihan

**Denda keterlambatan**:
Biaya yang timbul karena bahan dikembalikan setelah jatuh tempo.
_Avoid_: denda (bila maksudnya biaya penggantian)

**Biaya penggantian**:
Biaya yang timbul karena eksemplar hilang atau rusak saat dipinjam.
_Avoid_: denda buku hilang, harga buku

**Skorsing**:
Larangan sementara untuk meminjam karena pelanggaran atau tagihan yang belum diselesaikan.
_Avoid_: suspend, blokir akun
