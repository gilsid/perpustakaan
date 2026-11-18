# PROJECT_CONTEXT.md — Perpustakaan Digital

> Dokumen konteks proyek untuk menyusun **Work Breakdown Structure (WBS)** dan jadwal di **ProjectLibre**.
> Ditulis seolah proyek dibangun dari nol oleh sebuah tim selama **3 bulan (1 September – 30 November 2026)**.
> Seluruh isi fitur dan arsitektur diambil dari kode sumber di repositori ini; hal yang tidak bisa dibuktikan dari kode diberi label **(asumsi)**.

---

## 0. Catatan penting & daftar asumsi

1. **Premis "PHP murni + JSON" tidak ditemukan di kode.** Repositori yang dianalisis sudah berupa aplikasi **Laravel 13 + PostgreSQL** (lihat `composer.json`, `config/database.php` `DB_CONNECTION=pgsql`, `docker-compose.yml` PostgreSQL 16). Jejak penyimpanan JSON/file-based tidak ada di kode. Untuk kebutuhan narasi proyek, **migrasi data awal dari berkas JSON ke PostgreSQL diperlakukan sebagai asumsi** (diterjemahkan menjadi task migrasi data lewat seeder, lihat `4.4`) — bukan fakta yang bisa dibuktikan dari repositori.
2. Versi dari kode: **PHP ^8.3**, **laravel/framework ^13.17** (README menyebut "Laravel 13"), **Vite ^8**, **PHPUnit ^12**, **PostgreSQL 16** (Docker lokal).
3. **Fitur yang TIDAK ada di kode** (dilarang masuk WBS sebagai fitur): notifikasi/email transaksional, reset kata sandi via email, verifikasi email, ekspor laporan (PDF/Excel), API publik, manajemen stok fisik/multi-eksemplar, multi-cabang. **Modul denda & skorsing sudah ada** (ditambahkan 18 November 2026, lihat 2.3a) sehingga tidak lagi masuk daftar ini.
4. Estimasi effort adalah estimasi kasar berbasis ukuran kode; dianggap **(asumsi)** seluruhnya untuk keperluan WBS.
5. Jumlah hari kerja Sep–Nov 2026 ≈ **65 hari kerja** (13 minggu × 5 hari).

---

## 1. Ringkasan proyek

### 1.1 Tujuan

Membangun aplikasi **perpustakaan digital** untuk satu perpustakaan (asumsi: perpustakaan organisasi/kampus skala kecil–menengah), yang menyediakan:

- Katalog buku dengan **pencarian, filter, dan urutan** yang cepat.
- **Peminjaman buku digital (PDF)** dengan aturan pinjam yang bisa diatur, bukan sekadar katalog statis, termasuk **denda keterlambatan dan skorsing otomatis**.
- **Pembaca PDF langsung di peramban**, hanya terbuka selama pinjaman aktif.
- Interaksi pembaca: **favorit** dan **ulasan/rating** (dengan syarat pernah meminjam).
- **Panel admin** lengkap: kelola buku, kategori, peminjaman, ulasan, pengguna, dan log aktivitas.

### 1.2 Target pengguna (peran)

| Peran | Deskripsi | Yang bisa dilakukan (dari kode) |
|---|---|---|
| **Tamu** (belum masuk) | Pengunjung umum | Melihat home, katalog, kategori, detail buku; mendaftar; masuk |
| **Anggota** (`UserRole::Member`) | Pembaca terdaftar | Semua fitur tamu + pinjam/perpanjang/kembalikan buku PDF, baca PDF saat pinjaman aktif, favorit, tulis/ubah/hapus ulasan sendiri, halaman pinjaman & favorit & profil |
| **Admin** (`UserRole::Admin`, "pustakawan") | Pengelola perpustakaan | Semua halaman anggota area publik + dashboard, CRUD buku & kategori, kelola peminjaman (tandai selesai), moderasi ulasan, kelola pengguna (status, peran, reset sandi), log aktivitas. **Admin bisa membaca semua PDF, tetapi tidak bisa meminjam, memfavoritkan, atau mengulas** |

### 1.3 Ruang lingkup

**Termasuk:** katalog + pencarian/filter + 6 mode urutan; peminjaman dengan aturan terkonfigurasi; **denda keterlambatan + skorsing**; pembaca PDF berbasis kebijakan akses; favorit; ulasan 1–5 bintang; CRUD admin; manajemen pengguna; log aktivitas; halaman error kustom; pengujian otomatis.

**Di luar ruang lingkup (tidak ada di kode):** pembayaran daring/gateway, email notifikasi, ekspor laporan, API untuk aplikasi pihak ketiga, stok buku fisik. Denda dibayar tunai ke pustakawan lalu ditandai lunas di panel admin.

### 1.4 Tech stack

| Komponen | Pilihan | Sumber bukti |
|---|---|---|
| Bahasa | PHP **8.3+** (ekstensi: `pdo_pgsql`, `mbstring`, `fileinfo`, `gd`, dll.) | `README.md`, `composer.json` |
| Framework | **Laravel ^13.17** (Blade template, Eloquent ORM, FormRequest, Policy/Gate, middleware) | `composer.json` |
| Database | **PostgreSQL 13+** (lokal: `postgres:16-alpine` via Docker, port 5433) | `docker-compose.yml` |
| Fitur DB yang dipakai | `ILIKE`, `generate_series`, `pg_trgm` + indeks GIN, `CHECK`, `UNIQUE`, **unique index parsial**, `timestampTz` | `database/migrations/*` |
| Frontend | Blade + CSS murni (`resources/css/app.css`) + **JavaScript vanilla (peningkatan progresif)** — semua halaman tetap berfungsi tanpa JS | `resources/js/app.js` |
| Build aset | **Vite ^8** + `laravel-vite-plugin`; font **Literata** & **Atkinson Hyperlegible** dari `@fontsource` (self-hosted, tanpa Google Fonts) | `package.json`, `vite.config.js` |
| Gambar | PHP **GD** — sampul selalu di-encode ulang ke JPEG | `app/Services/BookMedia.php` |
| Sesi/cache/antrian | Driver **database** (`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`) | `.env.example` |
| Pengujian | **PHPUnit ^12** + Faker + Mockery; tes berjalan di PostgreSQL, disk palsu | `composer.json`, `phpunit.xml` |
| Kualitas kode | **Laravel Pint** (gaya kode), `Model::shouldBeStrict()` di luar produksi (deteksi N+1) | `composer.json`, `app/Providers/AppServiceProvider.php` |
| Dev ops | **Docker Compose** (hanya PostgreSQL), Git, Node 20.19+/22.12+ untuk build | `docker-compose.yml`, `README.md` |
| Integrasi pihak ketiga runtime | **Tidak ada.** Tanpa CDN, tanpa API eksternal, tanpa gateway email (mailer `log`). Font di-bundle saat build. | Kode |

---

## 2. Daftar fitur lengkap

Kompleksitas: **R** = rendah, **S** = sedang, **T** = tinggi.

### 2.1 Autentikasi & akun

| Fitur | Deskripsi singkat | File/halaman terkait | Komp. | Ketergantungan |
|---|---|---|---|---|
| Registrasi anggota | Form nama/email/sandi; email disimpan huruf kecil; rate limit `throttle:10,1`; dicatat ke log aktivitas | `Auth/RegisterController.php`, `Http/Requests/Auth/RegisterRequest.php`, `resources/views/auth/register.blade.php` | S | Tabel `users`, keamanan dasar |
| Masuk / keluar | Validasi via `LoginRequest`; **pembatasan 5 gagal per email+IP / 15 menit** dan 20 per IP; respons seragam (email terdaftar tak bisa ditebak); setelah masuk: admin → dashboard, anggota → home | `Auth/LoginController.php`, `Http/Requests/Auth/LoginRequest.php` (RateLimiter), `auth/login.blade.php` | S | Tabel `users`, sesi |
| Sesi & redirect | Sesi disimpan di database; regenerate saat login; guest dialihkan ke `/masuk` | `bootstrap/app.php`, `config/session.php` | R | Laravel |
| Profil anggota | Ubah nama & email di `/profil` | `Account/ProfileController.php`, `Http/Requests/Account/UpdateProfileRequest.php`, `account/profile.blade.php` | R | Auth |
| Ganti kata sandi | `/profil/sandi`; konfirmasi sandi lama; min. 8 karakter | `Account/PasswordController.php`, `UpdatePasswordRequest.php` | R | Auth |
| Wajib ganti sandi sementara | Akun hasil reset admin wajib ganti sandi dulu (`must_change_password`) sebelum memakai fitur lain; profil tetap bisa diakses | `Http/Middleware/RequirePasswordChange.php` (alias `password.fresh`) | S | Reset sandi admin |
| Akun nonaktif auto-logout | Akun yang dinonaktifkan langsung dikeluarkan dari semua sesi saat request berikutnya | `Http/Middleware/EnsureUserIsActive.php` | R | Manajemen pengguna |
| Command buat admin | `php artisan library:create-admin` (interaktif via Prompts; bisa juga upgrade akun yang sudah ada menjadi admin) | `app/Console/Commands/CreateAdmin.php` | R | Tabel `users` |

### 2.2 Katalog & konten publik

| Fitur | Deskripsi singkat | File/halaman terkait | Komp. | Ketergantungan |
|---|---|---|---|---|
| Halaman home | 6 buku terbaru, 6 berating tertinggi, daftar kategori + jumlah, statistik judul/digital | `HomeController.php`, `home.blade.php` | R | Model Book |
| Katalog + pencarian | Pencarian `ILIKE` pada judul/penulis/ISBN/penerbit (dengan indeks `pg_trgm`); relevansi mengurutkan judul > penulis | `CatalogController.php`, `app/Support/CatalogFilters.php`, `Book::scopeFilter`, `catalog/index.blade.php` | S–T | Skema DB, indeks trgm |
| Filter & urutan katalog | Filter: kategori (dengan hitungan per kategori), rentang tahun terbit, bahasa, "hanya digital". Urutan: relevan, terbaru, judul A–Z, rating, tahun, terpopuler. Paginasi 12/halaman, kustom `vendor/pagination/library` | `Book::scopeSortedBy`, `app/Enums/CatalogSort.php`, `config/library.php` (`per_page`) | S | Fitur sebelumnya |
| Daftar kategori | Halaman `/kategori` | `CategoryController.php`, `categories/index.blade.php` | R | Tabel `categories` |
| Detail buku | Info lengkap (penulis, ISBN, penerbit, tahun, bahasa, halaman, nomor klasifikasi, deskripsi), rating rata-rata + jumlah ulasan + jumlah peminjaman dalam 1 query (`withStats`), daftar ulasan, aksi pinjam/favorit/ulas | `BookController.php`, `books/show.blade.php`, `books/_favorite.blade.php` | S | Katalog, fitur pinjam/ulas/favorit |
| Kartu buku & komponen UI | Komponen Blade: kartu buku, sampul (dengan "warna nada" per kategori), badge, rating bintang, dialog konfirmasi, flash message, sprite ikon | `resources/views/components/*` | S | Desain CSS |

### 2.3 Peminjaman & pengembalian

| Fitur | Deskripsi singkat | File/halaman terkait | Komp. | Ketergantungan |
|---|---|---|---|---|
| Pinjam buku | Hanya buku ber-PDF; aturan dari `config/library.php`/`.env`: **14 hari**, **maks. 5 pinjaman aktif**, transaksional + `lockForUpdate` untuk mencegah race; anti duplikat via **unique index parsial** di database. Pinjaman yang lewat tenggat **tetap terbuka** dan memblokir peminjaman baru sampai dikembalikan | `app/Services/LoanService.php::borrow`, `LoanController.php`, `app/Exceptions/LoanException.php` | **T** | Tabel `loans`, Book, Auth |
| Perpanjangan (renew) | Maks. 1× per pinjaman (dikonfigurasi), menambah durasi standar | `LoanService::renew`, `LoanController::update` | S | Pinjam |
| Pengembalian mandiri | Anggota menandai sendiri buku dikembalikan; akses PDF langsung tertutup; **denda dihitung saat itu** bila melewati tenggat | `LoanService::giveBack`, `LoanController::destroy`, `app/Services/FineCalculator.php` | S | Pinjam |
| Status pinjaman turunan | Status **tidak disimpan**: Aktif (belum kembali, tenggat di masa depan), Berakhir (tenggat lewat → akses otomatis tertutup), Selesai (dikembalikan) — via `app/Enums/LoanStatus.php` + scope `active/expired/returned/finished/overdue` | `app/Models/Loan.php`, `app/Enums/LoanStatus.php` | S | Skema `loans` |
| Denda keterlambatan | Tarif di-**snapshot** ke pinjaman saat meminjam (`fine_rate`); hitung hari telat setelah masa tenggang 24 jam, dibulatkan ke atas, dibatasi cap. Nominal disimpan saat pinjaman ditutup | `app/Services/FineCalculator.php`, `LoanService::closeLoan`, kolom `fine_*` di `loans` | **T** | Pinjam, `config/library.php` |
| Skorsing otomatis | Telat N hari → `users.suspend_until` = sekarang + `hari × faktor` (cap 30 hari). Skorsing memblokir pinjaman baru | `LoanService::applySuspension`, `User::isSuspended` | S | Denda |
| Pemblokiran peminjaman | Anggota tidak bisa meminjam bila: (a) diskors, (b) punya denda belum lunas, atau (c) punya pinjaman lewat tenggat. Alasan ditampilkan di halaman detail buku | `LoanService::blockReasons`, `BookController` | S | Denda, Skorsing |
| Pelunasan & pembebasan denda | Admin menandai **lunas penuh** atau **membebaskan** dengan alasan wajib (≥5 karakter); pembebasan sekaligus mencabut skorsing. Nominal asli tetap tersimpan untuk audit | `Admin/LoanFineController.php`, `LoanService::settleFine/waiveFine`, `ActivityLog` | **T** | Denda |
| Daftar pinjaman anggota | Halaman `/pinjaman`: aktif, **terlambat (dengan tombol kembalikan)**, riwayat + status denda | `Account/LoanController.php`, `account/loans.blade.php`, komponen tab akun | R | Pinjam, Denda |

### 2.3a Denda & skorsing

Aturan (semua lewat `.env`, lihat `config/library.php`):

| Parameter | Bawaan | Arti |
|---|---|---|
| `FINE_RATE_PER_DAY` | 1000 | Rp per hari telat (snapshot per pinjaman) |
| `FINE_MAX` | 50000 | Cap denda per pinjaman |
| `FINE_GRACE_HOURS` | 24 | Bebas denda bila telat ≤ 24 jam |
| `FINE_SUSPEND_FACTOR` | 2 | 1 hari telat = 2 hari skorsing |
| `FINE_SUSPEND_MAX_DAYS` | 30 | Cap durasi skorsing |

- **Hari telat** = `ceil((dikembalikan − due_at − grace) / 24 jam)`, minimal 0. Hari kalender, bukan hari kerja, karena perpustakaan digital buka 24/7.
- **Nominal** = `min(hari telat × fine_rate, FINE_MAX)`. Tarif diambil dari snapshot pinjaman, bukan config saat ini.
- **Skorsing** mulai dihitung saat pinjaman dikembalikan (atau diakhiri admin), bukan saat tenggat lewat.
- Pinjaman yang sudah lewat tenggat **tidak** ditutup otomatis; baris tetap terbuka sebagai bukti keterlambatan. Sebelum fitur ini aktif, migration menutup semua pinjaman telat lama dengan `returned_at = due_at` agar tidak ada tagihan mundur.
- Pinjaman yang **masih berjalan** saat migration dijalankan diisi `fine_rate` dengan tarif aktif, supaya pengembalian telat setelah fitur aktif tidak menghasilkan denda Rp0.
- Alur uang: tunai di luar aplikasi → admin klik **Lunas**. Jika dibebaskan, nominal asli tetap tercatat dan `ActivityLog` menyimpan alasannya.
- Kolom terkait: `loans.fine_rate`, `late_days`, `fine_amount`, `fine_paid_at`, `fine_waived_at`, `fine_waived_by`; `users.suspend_until`. Status denda diturunkan (`FineStatus`) dan tidak disimpan.

### 2.4 Pembaca PDF (reader)

| Fitur | Deskripsi singkat | File/halaman terkait | Komp. | Ketergantungan |
|---|---|---|---|---|
| Kebijakan akses PDF | `BookPolicy::read`: admin selalu boleh; anggota hanya jika pinjaman aktif; buku tanpa PDF ditolak | `app/Policies/BookPolicy.php` | S | Loan, Book |
| Halaman pembaca | `/baca/{book}` membungkus PDF dalam iframe; menampilkan info pinjaman | `ReaderController::show`, `books/reader.blade.php`, layout `components/layouts/reader.blade.php` | S | Kebijakan akses |
| Penyajian berkas aman | `/berkas/{book}` menyajikan PDF dari **disk privat** (`storage/app/private/books`, di luar web root) dengan `BinaryFileResponse` + **HTTP Range** (lompat halaman tanpa unduh penuh); header `Cache-Control: private`, CSP `frame-ancestors 'self'` | `ReaderController::file`, `app/Services/BookMedia.php` | **T** | Kebijakan akses |

### 2.5 Favorit & ulasan

| Fitur | Deskripsi singkat | File/halaman terkait | Komp. | Ketergantungan |
|---|---|---|---|---|
| Favorit | Toggle simpan/hapus favorit; halaman `/favorit`; hanya anggota | `FavoriteController.php` (root & Account), `favorites` (tabel), `account/favorites.blade.php` | R | Book, Auth |
| Ulasan & rating | Rating 1–5 + komentar; **satu ulasan per anggota per buku** (UNIQUE di DB); `updateOrCreate`; `CHECK rating BETWEEN 1 AND 5`; **syarat: pernah meminjam bukunya** (`library.review_requires_loan`, default aktif); bisa diubah | `ReviewController::store`, `Http/Requests/StoreReviewRequest.php` | S | Loan, Book |
| Hapus ulasan | Pemilik atau admin (via `ReviewPolicy`); hapus oleh admin dicatat di log | `ReviewController::destroy`, `ReviewPolicy`, `Admin/ReviewController` | R | Ulasan |
| Moderasi ulasan (admin) | Daftar semua ulasan + hapus | `Admin/ReviewController.php`, `admin/reviews.blade.php` | R | Ulasan |

### 2.6 Panel admin

| Fitur | Deskripsi singkat | File/halaman terkait | Komp. | Ketergantungan |
|---|---|---|---|---|
| Guard admin | Gate `admin`/`member` didefinisikan di `AppServiceProvider`; grup rute `can:admin`; URL `/admin/...` | `app/Providers/AppServiceProvider.php`, `routes/web.php` | R | Auth |
| CRUD buku | Tambah/ubah/hapus; unggah **sampul** (validasi gambar, maks 3 MB, maks 800×1200, di-encode ulang JPEG via GD — SVG ditolak) dan **PDF** (maks 50 MB, dicek tanda tangan berkas `%PDF-` via aturan `PdfSignature`); berkas lama dihapus hanya setelah data tersimpan; hapus buku ikut menghapus berkas & ulasan (cascade); dicatat di log aktivitas | `Admin/BookController.php`, `Http/Requests/Admin/BookRequest.php`, `app/Services/BookMedia.php`, `app/Rules/PdfSignature.php`, `admin/books/*` | **T** | BookMedia, Category, log aktivitas |
| CRUD kategori | Nama + slug unik; hapus diproteksi FK `restrict` jika masih dipakai buku | `Admin/CategoryController.php`, `Http/Requests/Admin/CategoryRequest.php`, `admin/categories.blade.php` | R | Tabel `categories` |
| Kelola peminjaman | Daftar semua pinjaman, filter status (aktif/berakhir/selesai), cari nama/email anggota atau judul buku, 20/halaman; **tandai selesai** (menutup akses PDF) | `Admin/LoanController.php`, `admin/loans.blade.php` | S | Loan |
| Kelola pengguna | Daftar pengguna; aktif/nonaktif; ubah peran member↔admin; **reset sandi → sandi sementara + wajib ganti**; proteksi: admin tidak bisa menonaktifkan/menurunkan dirinya sendiri dan admin aktif terakhir; semua aksi dicatat di log | `Admin/UserController.php`, `UserStatusController.php`, `UserRoleController.php`, `UserPasswordResetController.php`, `admin/users.blade.php` | **T** | Auth, middleware akun |
| Log aktivitas | Tabel `activity_logs`: aksi, entitas, pesan, IP, pelaku; tercatat: daftar, tambah/ubah/hapus buku & kategori, akhir pinjaman, ubah peran, reset sandi, aktif/nonaktif akun, hapus ulasan, registrasi | `app/Models/ActivityLog.php`, `Admin/ActivityLogController.php`, `admin/logs.blade.php` | R | Semua aksi admin |
| Dashboard admin | Statistik: jumlah judul, judul digital, anggota, ulasan, pinjaman aktif, pinjaman yang tenggatnya ≤3 hari; **grafik peminjaman 14 hari terakhir** (SQL `generate_series`, zona waktu aplikasi); top 5 buku terpinjam; log terbaru; pinjaman terbaru | `Admin/DashboardController.php`, `admin/dashboard.blade.php` | S | Loan, Book, log aktivitas |
| Layar error kustom | 403/404/405/419/429/500/503; `PostTooLargeException` kembali ke form dengan pesan jelas (bukan halaman 413 kosong) | `resources/views/errors/*`, `bootstrap/app.php` | R | Desain |

### 2.7 Keamanan lintas aplikasi

| Fitur | Deskripsi singkat | File/halaman terkait | Komp. | Ketergantungan |
|---|---|---|---|---|
| Header keamanan + CSP | CSP tanpa `unsafe-inline` dan tanpa sumber eksternal; `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS saat HTTPS; dukungan Vite HMR di dev | `Http/Middleware/SecurityHeaders.php` | S | — |
| Validasi unggahan | Validasi dari **isi** berkas (aturan `image`, `mimetypes`, `PdfSignature`), bukan hanya ekstensi; sampul re-encode; resolusi maks 40 MP; pesan batas PHP yang jelas | `BookMedia.php`, `PdfSignature.php`, `BookRequest.php` | S | — |
| Integritas data di DB | Foreign key dengan `cascade/restrict/null`, `CHECK`, `UNIQUE`, unique index parsial (anti pinjaman ganda race condition) | `database/migrations/*` | S | — |
| Pengujian otomatis | **141 tes** di 14 berkas: Feature (Account, Auth, Catalog, Loan, Fine, Reader, ReviewAndFavorite, SecurityHeaders, CreateAdminCommand, Admin Access/BookManagement/Management) + Unit (CatalogFilters, FineCalculator); disk palsu agar tidak menyentuh `storage/`; butuh PostgreSQL (`ILIKE`, index parsial) | `tests/**` | S–T | Semua fitur |

---

## 3. Arsitektur dan struktur

### 3.1 Struktur folder (ringkas)

```
app/
  Enums/               UserRole, LoanStatus, FineStatus, CatalogSort
  Exceptions/          LoanException
  Http/
    Controllers/       tipis: validasi → service/model → view
      Admin/           Dashboard, Book, Category, Loan, Review, User, UserStatus,
                       UserRole, UserPasswordReset, ActivityLog
      Account/         Profile, Password, Loan, Favorite
      Auth/            Login, Register
      (publik)         Home, Catalog, Category, Book, Loan, Favorite, Review, Reader
    Middleware/        EnsureUserIsActive, RequirePasswordChange, SecurityHeaders
    Requests/          seluruh validasi form (Auth, Account, Admin, Review)
  Models/              User, Book, Category, Loan, Review, ActivityLog (scope + accessor)
  Policies/            BookPolicy (akses PDF), ReviewPolicy
  Providers/           AppServiceProvider (Gate, locale, pagination, strict mode)
  Rules/               PdfSignature
  Services/            LoanService, FineCalculator, BookMedia
  Support/             CatalogFilters, Query (pembantu input), Rupiah (format nominal)
  Console/Commands/    library:create-admin
bootstrap/app.php      middleware global, alias, penanganan exception
config/library.php     aturan pinjam, batas unggah, throttle login, per halaman
database/
  migrations/          users, cache, jobs, categories, books, reviews, favorites, loans, activity_logs,
                       + 2026_11_18 denda & skorsing
  factories/  seeders/ User, Category, Book, Loan, Review (+ PDF contoh)
lang/id/               terjemahan validasi & paginasi
resources/
  views/               layouts (app/admin/auth/reader), komponen Blade, halaman
  css/app.css          CSS murni (tanpa framework)
  js/app.js            JS vanilla: menu, filter autosubmit, toggle sandi, dll.
routes/web.php         semua rute bernama
tests/                 Feature + Unit (141 tes)
storage/app/private/books   PDF (di luar web root)
storage/app/public/covers   sampul (via symlink storage:link)
docker-compose.yml     PostgreSQL 16 (port host 5433)
```

### 3.2 Alur request

1. `public/index.php` → HTTP kernel Laravel.
2. Middleware grup `web`: CSRF, sesi (database), cookie, lalu *append* **`EnsureUserIsActive`** dan **`SecurityHeaders`** (CSP diberlakukan global). Alias **`password.fresh`** (`RequirePasswordChange`) dipasang pada rute terproteksi.
3. Router `routes/web.php`: rute publik → tamu (`guest`) → `auth` → `auth + password.fresh` → bagian `can:member` → bagian `can:admin` (`/admin`). Rute resource memakai verba Indonesia (`/baru`, `/ubah`).
4. Controller tipis: **FormRequest** memvalidasi → **Model/Service** (mis. `LoanService`, `BookMedia`) → **view Blade** → response.
5. PDF dilayani khusus oleh `ReaderController::file` (BinaryFileResponse + Range) setelah `BookPolicy` mengizinkan; sampul dilayani via disk publik (`storage:link`).
6. Aksi penting admin/registrasi menulis baris ke `activity_logs`.

### 3.3 Skema database (10 migration; 7 tabel domain + cache/jobs)

| Tabel | Kolom penting | Relasi & integritas |
|---|---|---|
| `users` | `name`, `email` (UNIQUE, lowercase), `password` (bcrypt, rounds 12), `role` enum `member/admin`, `is_active`, `must_change_password`, `last_login_at`, `suspend_until` (NULL = tidak diskors), `remember_token` | Induk untuk `loans`, `reviews`, `favorites`, `activity_logs`; indeks `suspend_until` |
| `categories` | `name` (80, UNIQUE), `slug` (100, UNIQUE) | `books.category_id` → `categories` **restrictOnDelete** |
| `books` | `title`, `author`, `isbn`(20), `publisher`(160), `published_year` (CHECK 1000–2200), `language`, `pages` (CHECK >0), `call_number`, `description`, `cover_path`, `pdf_path` (NULL = hanya katalog) | FK kategori; indeks `created_at`, `published_year`; **indeks GIN `pg_trgm`** pada `title` & `author` (berfungsi meski ekstensi gagal dipasang — ada fallback di migration) |
| `reviews` | `rating` (CHECK 1–5), `comment` | FK `book`, `user` (cascade); **UNIQUE(book_id, user_id)** |
| `favorites` | — | PK komposit `(user_id, book_id)`; FK cascade dua arah |
| `loans` | `borrowed_at` (default now), `due_at` (CHECK > borrowed_at), `returned_at` (NULL = aktif), `renewals`, `fine_rate` (snapshot tarif), `late_days`, `fine_amount`, `fine_paid_at`, `fine_waived_at`, `fine_waived_by` | FK `user`, `book`, `fine_waived_by` (**nullOnDelete**); indeks `(user_id, borrowed_at)`, `(user_id, fine_paid_at)`; **unique index parsial** `(user_id, book_id) WHERE returned_at IS NULL` (anti pinjaman ganda); **indeks parsial** `(user_id) WHERE fine_amount > 0 AND fine_paid_at IS NULL AND fine_waived_at IS NULL` (denda terbuka) |
| `activity_logs` | `action`(60), `entity_type`(40), `entity_id`, `message`, `ip`(45), `created_at` | FK `user` **nullOnDelete** |
| `cache`, `jobs`, `sessions` | tabel framework | Sesi & cache & antrian berbasis database |

**Status pinjaman tidak disimpan** — diturunkan dari kolom (`returned_at`, `due_at`) menjadi `Aktif / Berakhir / Selesai`. Hal yang sama berlaku untuk **status denda** (`FineStatus`: tanpa denda / belum lunas / lunas / dibebaskan): accessor, bukan kolom.

### 3.4 Integrasi pihak ketiga

**Tidak ada integrasi runtime.** Tidak ada API eksternal, CDN, gateway pembayaran, atau penyedia email (mailer `log`). Font di-bundle saat build lewat npm `@fontsource`. *Asumsi:* server produksi menggunakan SMTP server milik institusi bila kelak butuh email — saat ini di luar kode.

---

## 4. Rincian pekerjaan per fase siklus hidup (hierarki WBS)

Kolom: **ID | Fase | Task | Durasi (hari kerja) | Effort (jam) | Peran | Predecessor**.
Durasi = hari kerja kalender berjalan pada staf yang ditugaskan. Total effort langsung **±948 jam**; dengan overhead (rapat, koordinasi, riset) ±20–25% → **±1.180–1.230 jam efektif** *(asumsi)*.

| ID | Fase | Task | Durasi | Effort | Peran | Predecessor |
|---|---|---|---|---|---|---|
| **1.1** | Inisiasi & Perencanaan | Identifikasi kebutuhan pemangku kepentingan; survei praktik perpustakaan saat ini | 2 | 16 | PM, System Analyst | – |
| **1.2** | Inisiasi & Perencanaan | Project charter, ruang lingkup & anggaran | 1 | 8 | PM | 1.1 |
| **1.3** | Inisiasi & Perencanaan | Analisis kelayakan & pemilihan stack (Laravel, PostgreSQL, Vite, Docker) | 2 | 16 | System Analyst, Backend Programmer, DBA | 1.1 |
| **1.4** | Inisiasi & Perencanaan | Jadwal, struktur tim, daftar risiko & mitigasi | 2 | 12 | PM | 1.2 |
| **1.5** | Inisiasi & Perencanaan | Kick-off; lingkungan kerja (repo Git, Docker, mesin dev) | 1 | 8 | PM, Backend Programmer | 1.3, 1.4 |
| **2.1** | Analisis Kebutuhan | Wawancara & observasi proses pustakawan dan pembaca | 3 | 24 | System Analyst | 1.5 |
| **2.2** | Analisis Kebutuhan | Penyusunan SRS (fungsi, non-fungsi, keamanan, batas unggahan) | 3 | 28 | System Analyst, PM | 2.1 |
| **2.3** | Analisis Kebutuhan | Definisi aturan bisnis pinjam & ulasan (durasi 14 hari, maks 5 aktif, perpanjangan 1×, akses PDF hanya pinjaman aktif, ulasan wajib pernah pinjam) | 1 | 10 | System Analyst, Backend Programmer | 2.2 |
| **2.4** | Analisis Kebutuhan | Review & persetujuan SRS (**M2**) | 1 | 6 | PM, System Analyst, Pemilik Proyek | 2.3 |
| **3.1** | Desain (UI/UX) | Wireframe halaman publik (home, katalog, detail buku, kategori) | 2 | 16 | UI/UX Designer | 2.2 |
| **3.2** | Desain (UI/UX) | Wireframe area anggota (pinjaman, favorit, profil, pembaca PDF) | 1 | 12 | UI/UX Designer | 3.1 |
| **3.3** | Desain (UI/UX) | Wireframe panel admin (dashboard, buku, kategori, peminjaman, ulasan, pengguna, log) | 2 | 16 | UI/UX Designer | 3.1 |
| **3.4** | Desain (UI/UX) | Desain visual & komponen (tipografi Literata/Atkinson Hyperlegible, warna, kartu, badge, dialog) | 3 | 28 | UI/UX Designer | 3.2, 3.3 |
| **3.5** | Desain (UI/UX) | Uji kegunaan ringan & revisi desain | 1 | 10 | UI/UX Designer, QA | 3.4 |
| **3.6** | Desain (Database) | Perancangan ERD 7 tabel domain (+ cache/jobs/sessions) | 2 | 20 | DBA, System Analyst | 2.2 |
| **3.7** | Desain (Database) | Perancangan constraint & indeks (CHECK, UNIQUE, unique index parsial, pg_trgm/GIN) | 1 | 12 | DBA | 3.6 |
| **3.8** | Desain (Arsitektur) | Rancangan keamanan: kebijakan akses PDF, CSP/throttle, disk privat, validasi unggahan | 2 | 12 | Backend Programmer, System Analyst | 2.3 |
| **3.9** | Desain | Review & persetujuan desain (**M3**) | 1 | 8 | PM, System Analyst, DBA, UI/UX | 3.5, 3.7, 3.8 |
| **4.1** | Implementasi (Fondasi) | Inisialisasi Laravel, Docker PostgreSQL, `.env`, zona waktu `Asia/Jakarta`, locale `id` | 2 | 12 | Backend Programmer | 3.9 |
| **4.2** | Implementasi (Fondasi) | Pipeline aset Vite (CSS, JS progresif, font @fontsource) | 1 | 8 | Frontend Programmer | 4.1 |
| **4.3** | Implementasi (Database) | Migration 9 tabel + constraint + indeks (termasuk fallback pg_trgm) | 2 | 16 | DBA | 4.1 |
| **4.4** | Implementasi (Database) | **Migrasi data awal dari berkas JSON ke PostgreSQL** lewat seeder *(asumsi — tidak terbukti dari kode)* | 1 | 10 | DBA, Backend Programmer | 4.3 |
| **4.5** | Implementasi (Database) | Factory & seeder data contoh (7 buku + 2 akun + PDF contoh) | 1 | 6 | Backend Programmer | 4.3 |
| **4.6** | Implementasi (Autentikasi) | Registrasi & login (validasi, bcrypt, throttle 5×/15 menit, sesi database, log registrasi) | 3 | 20 | Backend Programmer | 4.3 |
| **4.7** | Implementasi (Autentikasi) | Middleware akun & header keamanan (EnsureUserIsActive, RequirePasswordChange, SecurityHeaders/CSP) | 2 | 12 | Backend Programmer | 4.6 |
| **4.8** | Implementasi (Autentikasi) | Halaman masuk & daftar (Blade + CSS, toggle kata sandi) | 2 | 12 | Frontend Programmer | 4.6, 4.2 |
| **4.9** | Implementasi (Katalog) | Model Book + scope katalog (filter, 6 urutan, withStats rating/peminjaman) | 2 | 16 | Backend Programmer | 4.3 |
| **4.10** | Implementasi (Katalog) | Halaman katalog (pencarian, panel filter, paginasi kustom 12/hal.) | 3 | 20 | Frontend Programmer | 4.9, 4.2 |
| **4.11** | Implementasi (Katalog) | Halaman home & daftar kategori | 1 | 10 | Frontend Programmer | 4.9 |
| **4.12** | Implementasi (Katalog) | Halaman detail buku (statistik, ulasan, aksi pinjam/favorit/ulas) | 2 | 16 | Frontend Programmer | 4.10 |
| **4.13** | Implementasi (Buku) | Layanan BookMedia: sampul re-encode JPEG (GD), PDF ke disk privat, hapus berkas | 2 | 14 | Backend Programmer | 4.1 |
| **4.14** | Implementasi (Buku) | CRUD buku admin (BookRequest, PdfSignature, batas 3 MB/50 MB, log aktivitas) | 3 | 24 | Backend Programmer | 4.13, 4.9 |
| **4.15** | Implementasi (Buku) | CRUD kategori (slug unik, proteksi hapus via FK restrict) | 1 | 8 | Backend Programmer | 4.14 |
| **4.16** | Implementasi (Buku) | Halaman admin buku & kategori | 2 | 16 | Frontend Programmer | 4.14, 4.15 |
| **4.17** | Implementasi (Peminjaman) | LoanService: pinjam transaksional, lockForUpdate, batas 5 aktif, anti-duplikat (index parsial); pinjaman lewat tenggat **tetap terbuka** (bukan ditutup otomatis) | 3 | 20 | Backend Programmer | 4.14 |
| **4.18** | Implementasi (Peminjaman) | Perpanjangan & pengembalian mandiri anggota (penutupan pinjaman transaksional) | 1 | 10 | Backend Programmer | 4.17 |
| **4.19** | Implementasi (Peminjaman) | Halaman pinjaman anggota (aktif, terlambat, riwayat) + panel peminjaman admin (filter status/denda, akhiri pinjaman) | 2 | 16 | Backend + Frontend | 4.18 |
| **4.20** | Implementasi (Reader) | BookPolicy + ReaderController: PDF privat, HTTP Range, header privat, akses hanya pinjaman aktif | 2 | 16 | Backend Programmer | 4.17 |
| **4.21** | Implementasi (Reader) | Halaman pembaca PDF (iframe, info tenggat) | 1 | 10 | Frontend Programmer | 4.20 |
| **4.22** | Implementasi (Komunitas) | Favorit: toggle + halaman favorit | 1 | 8 | Backend + Frontend | 4.12 |
| **4.23** | Implementasi (Komunitas) | Ulasan & rating (syarat pernah pinjam, updateOrCreate, CHECK 1–5, hapus milik/admin) | 2 | 14 | Backend Programmer | 4.12 |
| **4.24** | Implementasi (UI) | Komponen UI bersama (kartu buku, badge, rating, dialog konfirmasi, flash, paginasi) | 2 | 12 | Frontend Programmer | 4.10 |
| **4.25** | Implementasi (Admin) | Dashboard admin (statistik, tren 14 hari via generate_series, top buku, log terkini) | 2 | 18 | Backend + Frontend | 4.19, 4.24 |
| **4.26** | Implementasi (Admin) | Manajemen pengguna (status, peran, reset sandi sementara, proteksi admin terakhir/dirinya) | 2 | 16 | Backend Programmer | 4.7 |
| **4.27** | Implementasi (Admin) | Halaman admin pengguna & log aktivitas | 1 | 10 | Frontend + Backend | 4.26 |
| **4.28** | Implementasi (UI) | Halaman error kustom (403/404/419/429/500/503) + pesan ramah batas unggahan | 1 | 6 | Frontend Programmer | 4.2 |
| **4.29** | Implementasi (Ops) | Command `library:create-admin` | 1 | 4 | Backend Programmer | 4.6 |
| **4.30** | Implementasi (Integrasi) | Integrasi antarmodul, code review, Pint, terjemahan `lang/id` | 3 | 24 | Backend + Frontend, PM | 4.22, 4.23, 4.25, 4.27, 4.28, 4.29 |
| **4.31** | Implementasi | Perbaikan bug harian selama implementasi (berjalan paralel) | 5 | 20 | Backend + Frontend | 4.10 |
| **4.32** | Implementasi (Peminjaman) | **Denda & skorsing** (ditambahkan 18 Nov 2026): FineCalculator murni (grace 24 jam, `ceil` hari, cap nominal), snapshot tarif per pinjaman, skorsing otomatis, blokir pinjam 3 sebab, pelunasan/pembebasan denda oleh admin + log, migrasi penutupan pinjaman telat lama | 3 | 24 | Backend + Frontend | 4.19 |
| **5.1** | Pengujian | Rencana & kasus uji dari SRS *(dapat disusun paralel sejak fase desain)* | 2 | 12 | QA, System Analyst | 2.4 |
| **5.2** | Pengujian | **Unit test**: CatalogFilters, **FineCalculator** (batas grace, pembulatan, cap), LoanService, BookMedia, PdfSignature | 3 | 24 | QA, Backend Programmer | 4.18, 4.23, 4.32 |
| **5.3** | Pengujian | **Feature test** (141 skenario): auth, katalog, pinjam, **denda & skorsing**, pembaca, ulasan/favorit, admin, header keamanan, command | 5 | 40 | QA, Backend Programmer | 4.30, 4.32 |
| **5.4** | Pengujian | **Pengujian keamanan**: CSP, CSRF, throttling, unggahan berbahaya, eskalasi peran, akses PDF | 2 | 16 | QA, Backend Programmer | 5.3 |
| **5.5** | Pengujian | Perbaikan bug hasil pengujian | 4 | 32 | Backend + Frontend | 5.4 |
| **5.6** | Pengujian | **UAT** bersama pustakawan & anggota percontohan (**M7**) | 2 | 16 | QA, PM, Pengguna | 5.5 |
| **5.7** | Pengujian | Revisi hasil UAT | 2 | 14 | Backend + Frontend | 5.6 |
| **6.1** | Deployment | Server produksi: PHP 8.3 + ekstensi, PostgreSQL, HTTPS, document root `public/` | 2 | 14 | DBA, Backend Programmer | 5.5 |
| **6.2** | Deployment | Konfigurasi produksi (`APP_DEBUG=false`, `composer --no-dev`, `artisan optimize`, `storage:link`, naikkan `upload_max_filesize`) | 1 | 6 | Backend Programmer | 6.1 |
| **6.3** | Deployment | Migration + verifikasi data produksi | 1 | 8 | DBA | 6.2 |
| **6.4** | Deployment | Smoke test pasca-deployment | 1 | 8 | QA | 6.3 |
| **6.5** | Deployment | **Go-live** (**M8**) | 1 | 4 | PM, Backend Programmer | 6.4 |
| **7.1** | Penutupan | Dokumentasi teknis (README: instalasi, aturan pinjam, keamanan, pengujian, produksi) | 2 | 12 | Technical Writer, Backend | 5.5 |
| **7.2** | Penutupan | Manual pengguna anggota & pustakawan | 2 | 16 | Technical Writer | 5.7 |
| **7.3** | Penutupan | Pelatihan & serah terima pustakawan | 1 | 8 | PM, System Analyst | 6.5, 7.2 |
| **7.4** | Penutupan | Evaluasi proyek, lessons learned, penutupan administratif (**M9**) | 1 | 8 | PM | 7.3 |

**Rekapitulasi per fase** *(asumsi)*:

| Fase | Effort (jam) |
|---|---|
| 1. Inisiasi & Perencanaan | 60 |
| 2. Analisis Kebutuhan | 68 |
| 3. Desain | 134 |
| 4. Implementasi | 424 |
| 5. Pengujian | 154 |
| 6. Deployment | 40 |
| 7. Penutupan & Dokumentasi | 44 |
| **Total langsung** | **948** |

---

## 5. Peran tim yang dibutuhkan

| Peran | Jumlah *(saran)* | Tanggung jawab utama | Task yang menjadi bagiannya |
|---|---|---|---|
| **Project Manager** | 1 (paruh waktu) | Charter, jadwal, anggaran, risiko, koordinasi mingguan, komunikasi pemilik proyek, milestone | 1.1–1.5, 2.2 (bersama), 2.4, 3.9, 4.30, 5.6, 6.5, 7.3, 7.4 |
| **System Analyst** | 1 | Mengumpulkan & merumuskan kebutuhan, SRS, aturan bisnis, memvalidasi desain terhadap kebutuhan | 1.1, 1.3, 2.1–2.4, 3.1–3.3 (validasi), 3.6, 3.8, 5.1, 7.3 |
| **UI/UX Designer** | 1 | Wireframe & desain visual semua halaman (publik, anggota, admin), sistem komponen, uji kegunaan | 3.1–3.5, masukan ke 4.8–4.12, 4.16, 4.21, 4.24, 4.25, 4.27, 4.28 |
| **Backend Programmer** | 2 | Model, service (LoanService, FineCalculator, BookMedia), migration, controller, middleware, policy, validasi, command, log aktivitas | 1.3, 2.3, 3.7–3.8, 4.1, 4.3–4.7, 4.9, 4.13–4.15, 4.17–4.20, 4.23, 4.26, 4.29–4.32, 5.2–5.5, 6.1–6.2, 7.1 |
| **Frontend Programmer** | 1 | Blade, CSS, JS progresif, komponen UI, halaman semua peran, halaman error | 4.2, 4.8, 4.10–4.12, 4.16, 4.19–4.22, 4.24–4.25, 4.27–4.28, 4.30–4.32, 5.5 |
| **Database Administrator** | 1 (paruh waktu) | ERD, migration, constraint & indeks, kinerja query, migrasi data, backup, server DB produksi | 1.3, 3.6–3.7, 4.3–4.4, 6.1, 6.3 |
| **QA / Tester** | 1 | Rencana & kasus uji, unit/feature test, pengujian keamanan, UAT, smoke test | 3.5, 5.1–5.6, 6.4 |
| **Technical Writer** | 1 (paruh waktu) | Dokumentasi teknis, manual pengguna, materi pelatihan | 7.1–7.2 |

**Total saran: ±7 orang (±5,5 FTE efektif)** — PM, DBA, dan Technical Writer paruh waktu.

---

## 6. Milestone penting

| ID | Milestone | Kriteria keluar | Tanggal *(asumsi)* | Setelah task |
|---|---|---|---|---|
| **M1** | Kick-off & lingkungan kerja siap | Repo, Docker PostgreSQL, jadwal disepakati | 8 Sep 2026 | 1.5 |
| **M2** | SRS disetujui | SRS + aturan bisnis ditandatangani pemilik proyek | 15 Sep 2026 | 2.4 |
| **M3** | Desain disetujui | Wireframe/mockup + ERD + rancangan keamanan disetujui | 30 Sep 2026 | 3.9 |
| **M4** | Fondasi berfungsi | Database termigrasi + registrasi/masuk + unggah buku admin berjalan | ±10 Okt 2026 | 4.8, 4.14 |
| **M5** | MVP selesai (fitur inti) | Katalog + pinjam + perpanjang + kembali (denda bila telat) + baca PDF end-to-end | ±31 Okt 2026 | 4.21 |
| **M6** | Code freeze (implementasi tuntas) | Semua fitur terdaftar selesai, 141 tes hijau di CI lokal | 7 Nov 2026 | 4.30 |
| **M7** | UAT lulus | Semua bug mayor/minor ditutup; pustakawan menyetujui | 21 Nov 2026 | 5.6, 5.7 |
| **M8** | Go-live produksi | Situs hidup di domain produksi, HTTPS, smoke test lulus | 25 Nov 2026 | 6.5 |
| **M9** | Serah terima & penutupan | Dokumentasi, manual, pelatihan selesai; proyek ditutup | 30 Nov 2026 | 7.4 |

---

## 7. Risiko dan biaya non-SDM

### 7.1 Risiko utama

| ID | Risiko | Dampak | Kemungkinan | Mitigasi |
|---|---|---|---|---|
| R1 | Ekstensi `pg_trgm` tidak bisa dipasang di server produksi | Pencarian katalog lambat pada koleksi besar | Kecil | Sudah diantisipasi kode: migration punya fallback (RAISE NOTICE) — pencarian tetap jalan `ILIKE` biasa |
| R2 | Batas upload PHP bawaan 2 MB terlalu kecil untuk PDF buku | Unggahan gagal saat demo/produksi | Sedang | Dokumentasi `php.ini` (`upload_max_filesize=60M`, `post_max_size=64M`); `PostTooLargeException` → pesan ramah |
| R3 | **Tidak ada reset sandi via email** di kode | Beban admin tiap lupa sandi; risiko akun terkunci | Sedang | Alur reset admin tersedia (`users.reset-password` + wajib ganti sandi); edukasi pustakawan; *asumsi:* SMTP ditambahkan di fase berikutnya |
| R4 | PDF koleksi besar (50 MB/buku) → storage & bandwidth naik | Biaya server naik, unduhan lambat | Sedang | Disk privat + HTTP Range (hemat bandwidth); kuota storage dipantau DBA |
| R5 | Race condition peminjaman bersamaan | Pinjaman ganda/batas terlewati | Kecil | Sudah mitigasi: `lockForUpdate` + unique index parsial di DB |
| R6 | Bus factor: tim kecil, pengetahuan terpusat | Keterlambatan saat anggota absen | Sedang | Code review wajib (4.30), dokumentasi teknis (7.1), 141 tes sebagai pagar pengaman |
| R7 | Scope creep: tuntutan notifikasi email, ekspor laporan, gateway pembayaran | Jadwal 3 bulan meleset | Tinggi | Daftar eksplisit "di luar ruang lingkup" (Bab 0); usulan masuk backlog versi berikutnya |
| R8 | Akun contoh seeder (`admin@example.com`) tertinggal di produksi | Keamanan | Kecil | Checklist deployment (6.3) verifikasi & hapus/ganti |
| R9 | Zona waktu/jam salah konfigurasi → tenggat pinjaman bergeser | Pinjaman "berakhir" salah | Kecil | `APP_TIMEZONE=Asia/Jakarta` konsisten aplikasi + koneksi DB; dashboard memakai zona waktu aplikasi |
| R10 | CSP ketat mempersulit penambahan skrip pihak ketiga | Integrasi lanjutan terhambat | Sedang | Kebijakan desain: semua aset lokal; evaluasi CSP tiap penambahan |

### 7.2 Biaya non-SDM (estimasi kasar, rupiah) *(asumsi)*

| Item | Keterangan | Estimasi |
|---|---|---|
| Domain `.id` / `.com` | 1 tahun | Rp 150.000 – 300.000 |
| Server produksi (VPS 2 vCPU / 4 GB RAM / 80–120 GB SSD) | ±Rp 700.000 – 1.500.000/bulan; untuk 3 bulan proyek | Rp 2.100.000 – 4.500.000 |
| Sertifikat TLS | Let's Encrypt | Rp 0 |
| Lisensi software | Laravel, PostgreSQL, Vite, Docker, PHPUnit, font OFL — semuanya open source | Rp 0 |
| Figma (desain) | Paket gratis | Rp 0 |
| Alat kolaborasi (Git hosting, kanban) | Paket gratis | Rp 0 |
| Backup / storage tambahan | ±Rp 100.000 – 300.000/bulan × 3 | Rp 300.000 – 900.000 |
| Internet & listrik selama proyek | ±Rp 500.000/bulan × 3 | Rp 1.500.000 |
| Material pelatihan & dokumen cetak | Manual, serah terima | Rp 300.000 – 500.000 |
| **Subtotal proyek 3 bulan** | | **±Rp 4,3 – 7,7 juta** |
| Cadangan risiko (±10%) | | ±Rp 0,5 – 0,8 juta |
| **Total biaya non-SDM proyek** | | **±Rp 5 – 8,5 juta** |
| Ops tahun pertama (pasca proyek): VPS + domain + backup | 12 bulan | ±Rp 10 – 20 juta/tahun |

---

*Dokumen ini disusun dari analisis kode pada repositori `perpustakaan-digital-laravel` (root: `routes/web.php`, `app/`, `database/migrations/`, `config/library.php`, `tests/`) sebagai bahan WBS ProjectLibre. Angka effort, durasi, tanggal, dan biaya adalah estimasi/asumsi dan wajib divalidasi tim sebelum dikunci.*
