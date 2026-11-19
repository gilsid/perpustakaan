# Perpustakaan Digital (Laravel)

Aplikasi perpustakaan digital: katalog dengan pencarian dan filter, peminjaman buku PDF, denda keterlambatan
dan skorsing, pembaca PDF di peramban, favorit, ulasan, dan panel admin. Dibangun dengan **Laravel 13**,
**PostgreSQL**, dan **Vite**.

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
php artisan migrate --seed        # --seed opsional: memuat 7 buku contoh + 2 akun contoh
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
| `LOAN_DAYS` | 14 hari |
| `LOAN_MAX_ACTIVE` | 5 buku sekaligus |
| `LOAN_MAX_RENEWALS` | 1 kali, menambah `LOAN_DAYS` |

- Hanya buku yang punya PDF yang bisa dipinjam; buku tanpa PDF tampil sebagai "Hanya katalog".
- PDF hanya bisa dibuka saat pinjaman aktif; setelah tenggat lewat atau buku dikembalikan, akses otomatis tertutup.
- Pinjaman yang lewat tenggat **tidak** ditutup otomatis: anggota harus menekan "Kembalikan" agar keterlambatan tercatat.
- Ulasan hanya bisa ditulis anggota yang pernah meminjam bukunya (`library.review_requires_loan`).
- Admin bisa membaca semua PDF, tetapi tidak meminjam, menyimpan favorit, atau mengulas.

Zona waktu aplikasi dan koneksi database: `APP_TIMEZONE` (bawaan `Asia/Jakarta`).

## Denda & skorsing

Tarif denda di-*snapshot* ke setiap pinjaman saat meminjam, jadi mengubah `.env` tidak mengubah tagihan yang sudah berjalan.

| Pengaturan | Bawaan | Arti |
|---|---|---|
| `FINE_RATE_PER_DAY` | `1000` | Rp per hari telat |
| `FINE_MAX` | `50000` | Batas maksimum denda per pinjaman |
| `FINE_GRACE_HOURS` | `24` | Bebas denda bila terlambat ≤ 24 jam |
| `FINE_SUSPEND_FACTOR` | `2` | 1 hari telat = 2 hari skorsing |
| `FINE_SUSPEND_MAX_DAYS` | `30` | Batas maksimum durasi skorsing |

- **Hari telat** = `ceil((dikembalikan − due_at − grace) / 24 jam)`, minimal 0. Hari kalender, bukan hari kerja.
- **Nominal** = `min(hari telat × tarif, FINE_MAX)`. Dihitung sekali saat pinjaman ditutup (oleh anggota atau admin).
- **Skorsing** mulai berjalan saat pinjaman telat dikembalikan: `users.suspend_until = sekarang + hari telat × faktor` (maks. 30 hari).
- Anggota **tidak bisa meminjam** bila diskors, punya denda belum lunas, atau masih punya pinjaman lewat tenggat. Alasannya tampil di halaman buku.
- Uang dibayar tunai ke pustakawan; admin lalu menekan **Lunas** (penuh, nominal tidak bisa diedit) atau **Bebaskan** dengan alasan wajib (≥5 karakter). Membebaskan denda sekaligus mencabut skorsing.
- Denda yang dibebaskan tetap menyimpan nominal aslinya untuk audit, dan setiap pelunasan/pembebasan dicatat di `activity_logs`.
- Migration `2026_11_18_000001` menutup pinjaman yang sudah telat saat fitur ini dipasang (`returned_at = due_at`) agar tidak ada tagihan mundur. Pinjaman yang masih berjalan diisi tarif aktif supaya tidak menghasilkan denda Rp0 saat dikembalikan telat.

## Struktur

```
app/
  Enums/                 UserRole, LoanStatus, FineStatus, CatalogSort, ActivityAction, BorrowBlockReason
  Http/Controllers/      tipis: validasi (FormRequest) → service/model → view
    Admin/  Account/  Auth/
  Http/Middleware/       EnsureUserIsActive, RequirePasswordChange, SecurityHeaders
  Http/Requests/         seluruh validasi form
  Models/                User, Book, Category, Loan, Review, ActivityLog (scope + accessor)
  Policies/              BookPolicy (hak baca PDF), ReviewPolicy
  Services/              LoanService (alur pinjam), LoanClock (waktu), LoanAccess (akses buku),
                         BorrowEligibility (aturan boleh pinjam), FinePolicy (hitungan murni),
                         FineService (siklus denda), Audit (jejak aktivitas), BookMedia
  Rules/  Support/       PdfSignature; CatalogFilters, SearchTerm, BorrowBlock, BorrowStanding,
                         LoanClosure, Query, Rupiah
  Console/Commands/      library:create-admin
config/library.php       aturan peminjaman, denda, batas upload, throttle login
database/                migrations, factories, seeders (+ PDF contoh)
lang/id/                 terjemahan validasi
resources/               views (Blade: components/, layouts/), css, js
routes/web.php           semua rute (bernama)
tests/                   Feature + Unit (195 tes)
```

Aturan domain punya satu pemilik: `LoanClock` untuk waktu pinjaman, `LoanAccess` untuk akses anggota ke buku,
`BorrowEligibility` untuk boleh/tidaknya meminjam beserta alasannya, `FinePolicy` + `FineService` untuk denda dan
skorsing, `LoanClosure` untuk pesan hasil penutupan, `Audit` untuk log aktivitas, dan `SearchTerm` untuk pola
pencarian `ILIKE` di katalog maupun panel admin.

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
- Penetapan denda, pelunasan, dan pembebasan denda dicatat di `activity_logs` (termasuk alasan pembebasan).
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
php artisan migrate --force
php artisan storage:link
php artisan optimize          # cache config, route, event, view
```

Arahkan document root web server ke folder `public/`, teruskan semua URL ke `index.php`, dan pakai HTTPS.
Belum ada fitur lupa-sandi via email; admin mereset sandi dari halaman Pengguna.
