# Perpustakaan Digital (Laravel)

Aplikasi perpustakaan digital: katalog dengan pencarian dan filter, peminjaman buku PDF, pembaca PDF di peramban,
favorit, ulasan, dan panel admin. Dibangun dengan **Laravel 13**, **PostgreSQL**, dan **Vite**.

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
- Ulasan hanya bisa ditulis anggota yang pernah meminjam bukunya (`library.review_requires_loan`).
- Admin bisa membaca semua PDF, tetapi tidak meminjam, menyimpan favorit, atau mengulas.

Zona waktu aplikasi dan koneksi database: `APP_TIMEZONE` (bawaan `Asia/Jakarta`).

## Struktur

```
app/
  Enums/                 UserRole, LoanStatus, CatalogSort
  Http/Controllers/      tipis: validasi (FormRequest) → service/model → view
    Admin/  Account/  Auth/
  Http/Middleware/       EnsureUserIsActive, RequirePasswordChange, SecurityHeaders
  Http/Requests/         seluruh validasi form
  Models/                User, Book, Category, Loan, Review, ActivityLog (scope + accessor)
  Policies/              BookPolicy (hak baca PDF), ReviewPolicy
  Services/              LoanService (aturan pinjam, transaksi), BookMedia (sampul & PDF)
  Rules/  Support/       PdfSignature, CatalogFilters, Query
  Console/Commands/      library:create-admin
config/library.php       aturan peminjaman, batas upload, throttle login
database/                migrations, factories, seeders (+ PDF contoh)
lang/id/                 terjemahan validasi
resources/               views (Blade: components/, layouts/), css, js
routes/web.php           semua rute (bernama)
tests/                   Feature + Unit (105 tes)
```

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
