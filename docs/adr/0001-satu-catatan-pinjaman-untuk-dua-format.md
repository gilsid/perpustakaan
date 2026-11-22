# Satu catatan pinjaman untuk dua format

Pinjaman digital dan pinjaman eksemplar fisik disimpan dalam satu entitas `Loan` yang membedakan format lewat field `format`, bukan dalam dua entitas terpisah.

Pilihan ini mengorbankan kemurnian model domain. Eksemplar fisik punya barcode, lokasi, kondisi, dan biaya penggantian; pinjaman digital tidak punya itu. Memisahkan keduanya akan memberi tiap alur kolom dan aturannya sendiri. Kami memilih satu entitas karena anggota melihat satu riwayat pinjaman, denda dan hold memakai satu aturan, serta jejak audit bertumpu pada satu nomor pinjaman. Field khusus eksemplar dibiarkan nullable untuk pinjaman digital.

Konsekuensinya, kode yang berlaku hanya untuk fisik harus memeriksa format lebih dulu, dan kolom fisik tidak boleh diasumsikan terisi.
