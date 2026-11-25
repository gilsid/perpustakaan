# Perpustakaan Digital (Laravel)

Aplikasi perpustakaan: katalog dengan pencarian dan filter, peminjaman buku PDF, sirkulasi eksemplar fisik oleh petugas, hold, denda keterlambatan, biaya penggantian, dan skorsing. Pembaca PDF di peramban, favorit, ulasan, dan panel admin. Dibangun dengan **Laravel 13**, **PostgreSQL**, dan **Vite**.

## Kebutuhan

- PHP **8.3+** dengan ekstensi `pdo_pgsql`, `mbstring`, `fileinfo`, `gd`, `openssl`, `tokenizer`, `xml`, `ctype`
- PostgreSQL **13+** (paling mudah: lewat `docker compose up -d`, lihat di bawah) — Docker saja bisa
- Composer 2
- Node.js 20.19+ / 22.12+ (hanya untuk membangun CSS/JS; `public/build` tidak disimpan di repo)

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate

docker compose up -d      # PostgreSQL siap: user perpus/perpus, db perpustakaan_laravel, port 5433
php artisan migrate --seed        # --seed opsional: 7 buku, 2 akun, dan 10 eksemplar fisik contoh
php artisan storage:link          # supaya sampul buku bisa diakses
php artisan library:create-admin  # buat akun admin Anda sendiri (interaktif)

php artisan serve
```

Buka <http://127.0.0.1:8000>.

### Database manual (tanpa Docker)

Bila memakai PostgreSQL terpasang sendiri, buat database dan sesuaikan `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD` di `.env`:

```sql
CREATE USER perpus WITH PASSWORD 'sandi-kuat';
CREATE DATABASE perpustakaan_laravel OWNER perpus;
```

Akun contoh dari `--seed`: `admin@example.com` / `admin123` (admin) dan `user@example.com` / `password123` (anggota).
**Ganti atau hapus sebelum dipakai sungguhan.**

### Batas upload PDF

Bawaan PHP membatasi upload 2 MB, terlalu kecil untuk buku. `php artisan serve` tidak meneruskan opsi `-d`, jadi ubah sekali di `php.ini`
(lokasinya: `php --ini`), lalu jalankan ulang server:

```ini
upload_max_filesize = 60M
post_max_size = 64M
```

Batas aplikasi: PDF 50 MB, sampul 3 MB (`config/library.php`). Bila batas PHP terlewati, form menampilkan pesan yang jelas.

### Aset (CSS/JS/font)

Font (Literata dan Atkinson Hyperlegible) disajikan dari server sendiri lewat paket `@fontsource`, tanpa Google Fonts.

```bash
npm install
npm run build      # membangun ulang public/build
npm run dev        # server Vite dengan hot reload saat mengubah resources/css atau resources/js
```

## Aturan peminjaman

Diatur lewat `.env`:

| Pengaturan | Bawaan |
|---|---|
| `LOAN_DAYS` | 14 hari untuk pinjaman digital |
| `LOAN_PHYSICAL_DAYS` | 14 hari untuk eksemplar fisik |
| `LOAN_MAX_ACTIVE` | 5 buku sekaligus, digital dan fisik digabung |
| `LOAN_MAX_RENEWALS` | 1 kali, menambah durasi standar |
| `HOLD_MAX_ACTIVE` | 5 hold aktif per anggota |
| `HOLD_PICKUP_DAYS` | 7 hari jendela ambil sebelum hold kedaluwarsa |

- Nomor anggota dibuat otomatis saat daftar (`MP-000001`) dan bisa diubah petugas. `membership_expires_at` opsional; kosong berarti tidak kedaluwarsa.
- Hanya buku yang punya PDF yang bisa dipinjam digital; buku tanpa PDF tampil sebagai "Hanya katalog".
- PDF hanya bisa dibuka saat pinjaman **digital** aktif. Pinjaman fisik tidak membuka akses PDF.
- Pinjaman yang lewat tenggat **tidak** ditutup otomatis: anggota menekan "Kembalikan" untuk digital, atau petugas melakukan check-in untuk fisik.
- Ulasan hanya bisa ditulis anggota yang pernah meminjam bukunya (`library.review_requires_loan`).
- Admin bisa membaca semua PDF, tetapi tidak meminjam, menyimpan favorit, atau mengulas.

Zona waktu aplikasi dan koneksi database: `APP_TIMEZONE` (bawaan `Asia/Jakarta`).

## Sirkulasi fisik

Petugas membuka `/admin/sirkulasi`:

1. **Check-out**: pindai atau ketik nomor anggota, lalu barcode eksemplar. Sistem menolak bila kartu tidak ditemukan, eksemplar sedang dipinjam, atau anggota memiliki alasan pemblokiran.
2. **Check-in**: pindai barcode eksemplar, pilih kondisi **baik**, **rusak**, atau **hilang**. Copy baik kembali ke rak atau langsung ditahan untuk hold berikutnya. Copy rusak/hilang tetap tidak bisa dipinjam dan memunculkan biaya ganti.
3. **Hold**: anggota memasang hold dari halaman buku bila semua eksemplar tidak tersedia. Antrean urut permintaan. Copy yang kembali ditahan untuk hold pertama, diberi jendela ambil 7 hari, lalu ditawarkan ke anggota berikutnya bila tidak diambil.

Petugas juga bisa mengelola eksemplar di `/admin/eksemplar` (barcode, lokasi, status, biaya ganti), mengubah kartu anggota dari daftar pengguna, memperpanjang pinjaman fisik dari tabel peminjaman, dan melihat hold yang siap diambil di halaman sirkulasi.

## Denda & skorsing

Tarif denda di-*snapshot* ke setiap pinjaman saat meminjam, jadi mengubah `.env` tidak mengubah tagihan yang sudah berjalan.

| Pengaturan | Bawaan | Arti |
|---|---|---|
| `FINE_RATE_PER_DAY` | `1000` | Rp per hari telat |
| `FINE_MAX` | `50000` | Batas maksimum denda per pinjaman |
| `FINE_GRACE_HOURS` | `24` | Bebas denda bila terlambat ≤ 24 jam |
| `FINE_SUSPEND_FACTOR` | `2` | 1 hari telat = 2 hari skorsing |
| `FINE_SUSPEND_MAX_DAYS` | `30` | Batas maksimum durasi skorsing |
| `FINE_REPLACEMENT_DEFAULT` | `0` | Biaya ganti saat `book_copies.replacement_cost` kosong; `0` berarti tidak ada biaya |

- **Hari telat** = `ceil((dikembalikan − due_at − grace) / 24 jam)`, minimal 0. Hari kalender, bukan hari kerja.
- **Nominal** = `min(hari telat × tarif, FINE_MAX)`. Dihitung sekali saat pinjaman ditutup (oleh anggota atau admin).
- **Skorsing** mulai berjalan saat pinjaman telat dikembalikan: `users.suspend_until = sekarang + hari telat × faktor` (maks. 30 hari).
- Anggota **tidak bisa meminjam** bila diskors, punya denda belum lunas, atau masih punya pinjaman lewat tenggat. Alasannya tampil di halaman buku.
- Uang dibayar tunai ke pustakawan; admin lalu menekan **Lunas** (penuh, nominal tidak bisa diedit) atau **Bebaskan** dengan alasan wajib (≥5 karakter). Membebaskan denda sekaligus mencabut skorsing.
- Denda yang dibebaskan tetap menyimpan nominal aslinya untuk audit, dan setiap pelunasan/pembebasan dicatat di `activity_logs`.
- **Biaya penggantian** berbeda dari denda telat. Muncul saat check-in dengan kondisi rusak atau hilang, nominalnya dari eksemplar (atau `FINE_REPLACEMENT_DEFAULT`), dan bisa dilunasi atau dibebaskan dengan alasan lewat tabel peminjaman. Tagihan yang belum lunas, denda maupun biaya ganti, memblokir pinjam.
- Migration `2026_11_18_000001` menutup pinjaman yang sudah telat saat fitur ini dipasang (`returned_at = due_at`) agar tidak ada tagihan mundur. Pinjaman yang masih berjalan diisi tarif aktif supaya tidak menghasilkan denda Rp0 saat dikembalikan telat.

## Struktur

```
app/
  Enums/                 UserRole, LoanStatus, FineStatus, CatalogSort, ActivityAction,
                         BorrowBlockReason, LoanFormat, BookCopyStatus, BookCopyCondition, HoldStatus
  Http/Controllers/      tipis: validasi (FormRequest) → service/model → view
    Admin/  Account/  Auth/
  Http/Middleware/       EnsureUserIsActive, RequirePasswordChange, SecurityHeaders
  Http/Requests/         seluruh validasi form
  Models/                User, Book, BookCopy, Category, Loan, Hold, Review, ActivityLog
  Policies/              BookPolicy (hak baca PDF digital), ReviewPolicy
  Services/              LoanService, LoanClock, LoanAccess, BorrowEligibility, HoldService,
                         FinePolicy, FineService, Audit, BookMedia
  Rules/  Support/       PdfSignature; CatalogFilters, SearchTerm, BorrowBlock, BorrowStanding,
                         LoanClosure, Query, Rupiah
  Console/Commands/      library:create-admin
config/library.php       aturan peminjaman, hold, denda, batas upload, throttle login
database/                migrations, factories, seeders (+ PDF contoh)
GLOSSARY.md              istilah sirkulasi yang dipakai di kode dan antarmuka
docs/adr/                keputusan arsitektur
lang/id/                 terjemahan validasi
resources/               views (Blade: components/, layouts/), css, js
routes/web.php           semua rute (bernama)
tests/                   Feature + Unit (225 tes)
```

Aturan domain punya satu pemilik: `LoanClock` untuk waktu pinjaman, `LoanAccess` untuk akses anggota ke buku,
`BorrowEligibility` untuk boleh/tidaknya meminjam beserta alasannya, `HoldService` untuk antrean hold,
`FinePolicy` + `FineService` untuk denda, biaya ganti, dan skorsing, `BookCopyCondition` untuk kondisi saat
check-in, `LoanClosure` untuk pesan hasil penutupan, `Audit` untuk log aktivitas, dan `SearchTerm` untuk pola
pencarian `ILIKE` di katalog maupun panel admin. Istilah yang dipakai tercantum di `GLOSSARY.md`.

PDF disimpan di `storage/app/private/books` (di luar folder publik) dan hanya dilayani oleh `ReaderController`
setelah `BookPolicy` mengizinkan, dengan dukungan HTTP Range. Sampul di `storage/app/public/covers`.

## Keamanan

- CSRF, session regenerate, hashing sandi, dan escaping Blade dari Laravel.
- Pembatasan percobaan masuk: 5 gagal per email+IP dalam 15 menit; respons seragam agar email terdaftar tidak bisa ditebak.
- Unggahan divalidasi dari isi file (aturan `image`, `mimetypes`, `PdfSignature`); sampul selalu di-encode ulang ke JPEG; SVG ditolak.
- Akun yang dinonaktifkan langsung keluar dari semua sesi; admin tidak bisa menonaktifkan/menurunkan dirinya sendiri atau admin aktif terakhir.
- Kata sandi sementara hasil reset admin wajib diganti sebelum memakai fitur lain.
- Header CSP (tanpa `unsafe-inline`, tanpa sumber eksternal), `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
- Integritas data dijaga database: foreign key, `CHECK`, `UNIQUE`, dan unique index parsial yang mencegah pinjaman ganda.
- Penetapan denda, biaya ganti, hold, check-out, check-in, pelunasan, dan pembebasan dicatat di `activity_logs` (termasuk alasan pembebasan).
- Di luar produksi, `Model::shouldBeStrict()` membuat N+1 query langsung terdeteksi.

## Pengujian

Tes memakai PostgreSQL (fitur seperti `ILIKE` dan unique index parsial khusus PostgreSQL). Buat database kosong `perpustakaan_testing` dulu:

```bash
docker exec perpus-db psql -U perpus -d postgres -c "CREATE DATABASE perpustakaan_testing OWNER perpus"
```

```bash
php artisan test            # atau: vendor/bin/phpunit
vendor/bin/pint             # merapikan gaya kode
```

Tes memakai disk palsu, jadi tidak menyentuh berkas di `storage/`.

## Produksi

```bash
# .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://domain-anda
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize          # cache config, route, event, view
```

Arahkan document root web server ke folder `public/`, teruskan semua URL ke `index.php`, dan pakai HTTPS.
`public/build` tidak disimpan di git, jadi aset harus dibangun di server setiap deploy.

Pasang satu entri cron agar hold yang lewat masa ambil dilepas tanpa menunggu pengunjung membuka halaman.

```cron
* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

Perintahnya juga bisa dijalankan manual dengan `php artisan library:expire-holds`.
Akun `admin@example.com` dan `user@example.com` bawaan seeder hanya untuk demo lokal. Ganti kata sandi dan hapus akun contoh sebelum memakai aplikasi sungguhan.
Belum ada fitur lupa-sandi via email; admin mereset sandi dari halaman Pengguna.
