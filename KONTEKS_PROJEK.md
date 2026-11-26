# KONTEKS PROJEK: Sistem Perpustakaan Digital

Dokumen konteks Projek Akhir Rekayasa Perangkat Lunak. Seluruh fakta diambil dari repositori. Bila suatu hal tidak ditemukan di kode, ditulis **TIDAK DITEMUKAN**.

Root repositori: [/home/utopia/repos/perpustakaan-digital-laravel](/home/utopia/repos/perpustakaan-digital-laravel). Semua sitasi file di bawah relatif terhadap root ini.

---

## 1. RINGKASAN

### Tujuan sistem

Aplikasi perpustakaan berbasis web yang mengelola katalog buku, peminjaman buku digital (PDF), sirkulasi eksemplar fisik, hold/antrean, denda keterlambatan, biaya penggantian, skorsing, favorit, ulasan, dan panel administrasi. Dibangun dengan Laravel 13 dan PostgreSQL. Sumber: `README.md:1-3`, `composer.json:2-5`.

### Aktor dan peran

Peran didefinisikan oleh kolom `users.role` dengan CHECK `member`/`admin` (`database/migrations/0001_01_01_000000_create_users_table.php:17,25`) dan Gate di `app/Providers/AppServiceProvider.php:35-36`.

| Aktor | Penjelasan | Akses utama |
|---|---|---|
| Publik | Pengunjung tanpa login | Beranda, katalog, kategori, detail buku (`routes/web.php:18-22`) |
| Tamu (`guest`) | Calon anggota | Halaman masuk dan daftar (`routes/web.php:25-30`) |
| Anggota (`member`) | Pemustaka terdaftar, punya nomor anggota | Rak pinjaman, pinjam digital, perpanjang, kembalikan, hold, favorit, ulasan, profil (`routes/web.php:34-63`) |
| Petugas / Admin (`admin`) | Pengelola perpustakaan | Dasbor, CRUD buku/eksemplar/kategori, sirkulasi fisik, peminjaman, denda, pengguna, ulasan, log (`routes/web.php:65-97`) |

Middleware global: `web`, `EnsureUserIsActive`, `SecurityHeaders` (`bootstrap/app.php:23-26`). Middleware `password.fresh` memaksa ganti sandi sementara (`bootstrap/app.php:28-30`).

### Fitur nyata per peran

**Publik**
- Beranda dengan statistik dan rekomendasi (`app/Http/Controllers/HomeController.php:11`, `resources/views/home.blade.php:5-89`).
- Katalog dengan pencarian, filter kategori/tahun/bahasa/format, pengurutan, paginasi (`app/Http/Controllers/CatalogController.php:14-41`, `app/Support/CatalogFilters.php:21-43`).
- Daftar kategori (`app/Http/Controllers/CategoryController.php:10`, `resources/views/categories/index.blade.php:7-16`).
- Detail buku beserta status pinjam, hold, favorit, dan ulasan (`app/Http/Controllers/BookController.php:20-57`).

**Anggota**
- Registrasi otomatis membuat nomor anggota `MP-<id 6 digit>` dan login otomatis (`app/Http/Controllers/Auth/RegisterController.php:23-36`, `app/Models/User.php:54-57`).
- Masuk/keluar dengan throttle dan regenerasi sesi (`app/Http/Controllers/Auth/LoginController.php:14-47`, `app/Http/Requests/Auth/LoginRequest.php:59-105`).
- Pinjam buku digital, perpanjang sekali, kembalikan mandiri (`app/Http/Controllers/LoanController.php:16,30,42`, `app/Services/LoanService.php:33-62,230-261,136-155`).
- Baca PDF hanya saat pinjaman digital aktif (`app/Policies/BookPolicy.php:14-21`, `app/Http/Controllers/ReaderController.php:22-53`).
- Pasang dan batalkan hold fisik (`app/Http/Controllers/HoldController.php:16,27`, `app/Services/HoldService.php:25-78`).
- Rak pinjaman dan favorit (`app/Http/Controllers/Account/LoanController.php:19`, `app/Http/Controllers/Account/FavoriteController.php:11`).
- Menulis/mengubah satu ulasan per buku dan menghapus ulasan sendiri (`app/Http/Controllers/ReviewController.php:21-49`, `app/Policies/ReviewPolicy.php:10-13`).
- Profil dan ganti sandi (`app/Http/Controllers/Account/ProfileController.php:13-18`, `app/Http/Controllers/Account/PasswordController.php:11-19`).

**Admin / Petugas**
- Dasbor statistik dan grafik (`app/Http/Controllers/Admin/DashboardController.php:17`).
- CRUD buku, eksemplar, kategori (`app/Http/Controllers/Admin/BookController.php:25-118`, `BookCopyController.php:21-100`, `CategoryController.php:16-52`).
- Sirkulasi fisik: check-out kartu + barcode, check-in barcode + kondisi (`app/Http/Controllers/Admin/CirculationController.php:25-99`).
- Pengelolaan peminjaman, perpanjangan petugas, pembayaran/pembebasan denda dan biaya ganti (`app/Http/Controllers/Admin/LoanController.php:22-78`, `LoanFineController.php:18-75`).
- Pengelolaan pengguna: status aktif, peran, kartu anggota, reset sandi (`app/Http/Controllers/Admin/UserStatusController.php:17`, `UserRoleController.php:18`, `MembershipController.php:17-34`, `UserPasswordResetController.php:17-27`).
- Moderasi ulasan dan log aktivitas (`app/Http/Controllers/Admin/ReviewController.php:17-42`, `ActivityLogController.php:13-29`).

Tidak ada rute API (`routes/api.php`: **TIDAK DITEMUKAN**).

---

## 2. ATURAN BISNIS

### 2.1 Batas pinjam, lama pinjam, perpanjangan

| Aturan | Sumber |
|---|---|
| Masa pinjam digital 14 hari, fisik 14 hari | `config/library.php:11-12` |
| Maksimal 5 pinjaman aktif, kuota digital + fisik digabung | `config/library.php:13`, `app/Services/LoanAccess.php:47-50`, `app/Services/BorrowEligibility.php:67-74,101-107` |
| Maksimal 1 kali perpanjangan | `config/library.php:14`, `app/Models/Loan.php:207-210` |
| Jendela "jatuh tempo dekat" 3 hari | `config/library.php:15`, `app/Services/LoanClock.php:51-60` |
| Pinjam digital wajib punya PDF dan anggota memenuhi syarat | `app/Services/LoanService.php:33-62`, `app/Services/BorrowEligibility.php:85-95` |
| Pinjam fisik: eksemplar harus tersedia, `book_id` cocok, bukan milik hold orang lain | `app/Services/BorrowEligibility.php:82-95`, `app/Services/LoanService.php:80-98` |
| Satu buku hanya boleh satu pinjaman terbuka per anggota | `database/migrations/2026_01_01_000005_create_loans_table.php:27-29` |
| Satu eksemplar hanya boleh satu pinjaman terbuka | `database/migrations/2026_11_20_000002_add_hybrid_circulation_fields.php:20-23` |
| Perpanjangan digital: hanya pinjaman aktif, bukan fisik, ditolak bila ada hold, batas renewals | `app/Services/LoanService.php:230-261` |
| Perpanjangan fisik oleh petugas: pinjaman belum kembali, ditolak bila ada hold, batas renewals | `app/Services/LoanService.php:264-294` |
| Pengecekan standar/kuota saat perpanjangan | **TIDAK DITEMUKAN** |

### 2.2 Status peminjaman dan denda

| Aturan | Sumber |
|---|---|
| Status pinjaman diturunkan: `Dikembalikan` bila `returned_at` ada, `Berakhir` bila lewat tenggat, otherwise `Aktif` | `app/Models/Loan.php:165-172` |
| Aktif = `returned_at IS NULL` dan `due_at > now()` | `app/Models/Loan.php:110-113`, `app/Services/LoanClock.php:27-30` |
| Masa tenggang 24 jam sebelum denda dihitung | `config/library.php:28`, `app/Services/LoanClock.php:32-43` |
| Denda = `min(late_days * fine_rate, fine_max)`, tarif di-snapshot per pinjaman | `app/Services/FinePolicy.php:28-41`, `config/library.php:26-27` |
| Skorsing = `min(late_days * suspend_factor, suspend_max_days)` | `app/Services/FinePolicy.php:44-47`, `config/library.php:29-30` |
| Skorsing tidak pernah dipendekkan saat ada keterlambatan baru | `app/Services/FineService.php:126-144` |
| Status denda diturunkan: `None`, `Unpaid`, `Paid`, `Waived`; `Waived` menang atas `Paid` | `app/Enums/FineStatus.php:21-30` |
| Hanya denda `Unpaid` yang bisa dibayar atau dibebaskan | `app/Enums/FineStatus.php:32-40` |
| Membayar denda tidak mencabut skorsing; membebaskan denda mencabut skorsing | `app/Services/FineService.php:88-95,97-124` |
| Biaya ganti = `book_copies.replacement_cost` atau default config; dibebaskan/dibayar terpisah | `app/Services/FineService.php:52-83`, `config/library.php:31` |
| Total tunggakan = denda belum lunas + biaya ganti belum lunas | `app/Services/FineService.php:44-50`, `app/Services/BorrowEligibility.php:48-51` |
| Alasan pembebasan wajib 5 sampai 255 karakter | `app/Http/Controllers/Admin/LoanFineController.php:32-37,61-66` |
| Pesan anggota/admin saat pinjaman ditutup | `app/Support/LoanClosure.php:20-46` |

### 2.3 Hold

| Aturan | Sumber |
|---|---|
| Maksimal 5 hold aktif per anggota | `config/library.php:35`, `app/Services/HoldService.php:46-48` |
| Hold hanya bila buku punya eksemplar dan semua eksemplar tidak tersedia | `app/Services/HoldService.php:38-44` |
| Anggota dengan pinjaman terbuka pada judul sama tidak boleh hold | `app/Services/HoldService.php:30-32` |
| Antrean FIFO berdasarkan `id`; anggota yang tidak memenuhi syarat dilewati | `app/Services/HoldService.php:80-93` |
| Masa ambil 7 hari sejak eksemplar ditahan | `config/library.php:36`, `app/Services/HoldService.php:123-141` |
| Hold kedaluwarsa dilepas, eksemplar tersedia, antrean berikutnya dipromosikan | `app/Services/HoldService.php:154-166,182-201` |
| Expiry dijalankan saat halaman dibuka, saat check-out, dan terjadwal tiap jam | `app/Services/HoldService.php:107-118`, `app/Services/LoanService.php:77-78`, `bootstrap/app.php:32-34` |
| Eksemplar `on_hold` hanya bisa di-check-out oleh pemegang hold | `app/Services/LoanService.php:80-98` |
| Hold aktif terpakai bila anggota mengambil eksemplar yang tersedia langsung | `app/Services/LoanService.php:80-118`, `app/Services/HoldService.php:143-152` |
| Pembatalan hanya untuk hold pending, oleh pemegang atau admin | `app/Services/HoldService.php:63-78` |
| Satu hold pending per anggota per buku | `database/migrations/2026_11_22_000001_create_holds_table.php:32-35` |
| Hold untuk buku digital | **TIDAK DITEMUKAN** |

### 2.4 Ulasan, rating, favorit, kategori

| Aturan | Sumber |
|---|---|
| Ulasan hanya untuk anggota, rating wajib 1 sampai 5, komentar maks 1000 | `app/Http/Requests/StoreReviewRequest.php:9-23` |
| Wajib pernah meminjam buku bila `review_requires_loan=true` | `config/library.php:40`, `app/Http/Controllers/ReviewController.php:21-35`, `app/Services/LoanAccess.php:29-35` |
| Satu ulasan per anggota per buku, bisa ditulis ulang | `app/Http/Controllers/ReviewController.php:30-33`, `database/migrations/2026_01_01_000003_create_reviews_table.php:20` |
| Rating rata-rata dibulatkan 1 desimal; 0 bila tanpa ulasan | `app/Models/Book.php:94-103` |
| Hapus ulasan oleh pemilik atau admin | `app/Policies/ReviewPolicy.php:10-13`, `app/Http/Controllers/Admin/ReviewController.php:36-42` |
| Alur persetujuan/moderation ulasan | **TIDAK DITEMUKAN** |
| Favorit idempoten, satu pasangan anggota-buku | `app/Http/Controllers/FavoriteController.php:11-22`, `database/migrations/2026_01_01_000004_create_favorites_table.php:16` |
| Kategori tidak bisa dihapus bila masih dipakai buku | `app/Http/Controllers/Admin/CategoryController.php:41-52` |
| Slug kategori otomatis dari nama, unik dengan akhiran `-2`, `-3`, dst | `app/Models/Category.php:16-35` |
| Nama dan slug kategori unik di database | `database/migrations/2026_01_01_000001_create_categories_table.php:13-14` |

### 2.5 Eksemplar, kondisi, dan biaya penggantian

| Aturan | Sumber |
|---|---|
| Kode eksemplar unik, maks 40 karakter | `database/migrations/2026_11_20_000001_create_book_copies_table.php:15` |
| Status eksemplar: `available`, `loan`, `on_hold`, `lost`, `damaged`, `withdrawn` | `database/migrations/2026_11_22_000002_rename_reserved_copy_status.php:12-15`, `app/Enums/BookCopyStatus.php:6-13` |
| Hanya `available` yang bisa dipinjam | `app/Enums/BookCopyStatus.php:38-41` |
| Kondisi check-in: Baik ke `Available`, Rusak ke `Damaged`, Hilang ke `Lost` | `app/Enums/BookCopyCondition.php:11-18` |
| Kondisi check-in wajib dipilih, tidak ada nilai default | `resources/views/admin/circulation.blade.php:34-41`, `app/Http/Controllers/Admin/CirculationController.php:74-80` |
| Eksemplar sedang dipinjam atau ditahan tidak bisa dihapus atau diset `available` | `app/Http/Controllers/Admin/BookCopyController.php:69-100` |
| Biaya penggantian per eksemplar, bisa dibayar atau dibebaskan | `database/migrations/2026_11_22_000003_add_replacement_costs.php:11-20`, `app/Services/LoanService.php:334-361` |
| Alur perbaikan/pembuangan eksemplar `damaged`/`lost`/`withdrawn` | **TIDAK DITEMUKAN** |

### 2.6 Kartu anggota dan keanggotaan

| Aturan | Sumber |
|---|---|
| Nomor anggota `MP-` + id 6 digit dibuat saat registrasi | `app/Models/User.php:54-57`, `app/Http/Controllers/Auth/RegisterController.php:29` |
| Nomor anggota unik | `database/migrations/2026_11_20_000002_add_hybrid_circulation_fields.php:26` |
| Admin bisa mengubah nomor dan tanggal habis keanggotaan | `app/Http/Controllers/Admin/MembershipController.php:17-34` |
| Anggota bisa mengubah nomor sendiri | **TIDAK DITEMUKAN** |
| Tanpa nomor atau keanggotaan kedaluwarsa memblokir pinjam | `app/Services/BorrowEligibility.php:35-43`, `app/Support/BorrowBlock.php:72-78` |
| Keanggotaan boleh tanpa tanggal habis (nullable) | `database/migrations/2026_11_20_000002_add_hybrid_circulation_fields.php:27` |

### 2.7 Pencarian dan audit

- Query dibatasi 100 karakter, wildcard `%`, `_`, `\` di-escape (`app/Support/Query.php:14-39`, `app/Support/SearchTerm.php:16-41`).
- Pencarian memakai `ILIKE` pada judul, penulis, ISBN, penerbit (`app/Models/Book.php:123-139`).
- Indeks trigram GIN untuk judul dan penulis, bergantung ekstensi `pg_trgm` (`database/migrations/2026_01_01_000002_create_books_table.php:35-46`).
- Audit ditulis terpusat lewat `Audit::record()` dengan actor, aksi, entitas, pesan, dan IP (`app/Services/Audit.php:191-201`).
- Daftar 32 aksi audit ada di `app/Enums/ActivityAction.php:9-42`.
- Ulasan dibuat/diubah, favorit, dan hapus ulasan oleh pemilik tidak diaudit (`app/Http/Controllers/ReviewController.php:42-46`, `FavoriteController.php:11-22`).

### 2.8 Nilai konfigurasi

Sumber: `config/library.php`.

| Kunci | Nilai | Baris |
|---|---|---|
| `loan.days` | 14 | `:11` |
| `loan.physical_days` | 14 | `:12` |
| `loan.max_active` | 5 | `:13` |
| `loan.max_renewals` | 1 | `:14` |
| `loan.due_soon_days` | 3 | `:15` |
| `fine.rate_per_day` | 1000 | `:26` |
| `fine.max` | 50000 | `:27` |
| `fine.grace_hours` | 24 | `:28` |
| `fine.suspend_factor` | 2 | `:29` |
| `fine.suspend_max_days` | 30 | `:30` |
| `fine.replacement_default` | 0 | `:31` |
| `hold.max_active` | 5 | `:35` |
| `hold.pickup_days` | 7 | `:36` |
| `review_requires_loan` | true | `:40` |
| `per_page` / `admin_per_page` | 12 / 20 | `:42-43` |
| `uploads.cover_max_kb` | 3072 | `:51` |
| `uploads.pdf_max_kb` | 51200 | `:52` |
| `uploads.cover_max_width` / `cover_max_height` | 800 / 1200 | `:53-54` |
| `auth.min_password` | 8 | `:63` |
| `auth.max_attempts` / per IP | 5 / 20 | `:64-65` |
| `auth.lockout_minutes` | 15 | `:66` |

---

## 3. SKEMA DATABASE LENGKAP

Sumber: `database/migrations/` dan verifikasi langsung dengan `pg_dump --schema-only` pada database `perpustakaan_testing`. Tabel bawaan framework yang dikecualikan: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`. Tabel `users` disertakan karena merupakan tabel domain.

Catatan umum: semua kunci asing memiliki `ON UPDATE NO ACTION` (default PostgreSQL). `unsignedInteger`/`unsignedSmallInteger`/`unsignedBigInteger` tidak menghasilkan tipe unsigned di PostgreSQL.

### 3.1 Tabel `users`

Sumber: `database/migrations/0001_01_01_000000_create_users_table.php:12-25`, `2026_11_18_000001_add_fines_to_loans_and_suspend_to_users.php:30-33`, `2026_11_20_000002_add_hybrid_circulation_fields.php:25-28`.

| Kolom | Tipe SQL | Null | Default |
|---|---|---|---|
| id | bigint, identity/sequence | tidak | `nextval('users_id_seq')` |
| name | varchar(120) | tidak | - |
| email | varchar(190) | tidak | - |
| password | varchar(255) | tidak | - |
| role | varchar(10) | tidak | `'member'` |
| is_active | boolean | tidak | `true` |
| must_change_password | boolean | tidak | `false` |
| last_login_at | timestamptz | ya | - |
| remember_token | varchar(100) | ya | - |
| created_at / updated_at | timestamptz | ya | - |
| suspend_until | timestamptz | ya | - |
| member_number | varchar(30) | ya | - |
| membership_expires_at | timestamptz | ya | - |

PK: `id`. UNIQUE: `email`, `member_number`. CHECK: `users_role_check CHECK (role IN ('member','admin'))`. INDEX: `users_suspend_until_index (suspend_until)`. FK: tidak ada.

### 3.2 Tabel `categories`

Sumber: `database/migrations/2026_01_01_000001_create_categories_table.php:11-16`.

| Kolom | Tipe SQL | Null | Default |
|---|---|---|---|
| id | bigint | tidak | sequence |
| name | varchar(80) | tidak | - |
| slug | varchar(100) | tidak | - |
| created_at / updated_at | timestamptz | ya | - |

PK: `id`. UNIQUE: `name`, `slug`. Tidak ada FK, CHECK, atau indeks tambahan.

### 3.3 Tabel `books`

Sumber: `database/migrations/2026_01_01_000002_create_books_table.php:12-46`.

| Kolom | Tipe SQL | Null | Default |
|---|---|---|---|
| id | bigint | tidak | sequence |
| title | varchar(255) | tidak | - |
| author | varchar(255) | tidak | - |
| isbn | varchar(20) | ya | - |
| publisher | varchar(160) | ya | - |
| published_year | smallint | ya | - |
| language | varchar(40) | ya | - |
| pages | integer | ya | - |
| call_number | varchar(40) | ya | - |
| category_id | bigint | ya | - |
| description | text | tidak | `''` |
| cover_path | varchar(255) | ya | - |
| pdf_path | varchar(255) | ya | - |
| created_at / updated_at | timestamptz | ya | - |

PK: `id`. FK: `category_id` ke `categories(id)` ON DELETE RESTRICT. CHECK: `books_year_check (published_year BETWEEN 1000 AND 2200)`, `books_pages_check (pages > 0)`. INDEX: `books_created_at_index`, `books_published_year_index`, `books_title_trgm` GIN, `books_author_trgm` GIN.

### 3.4 Tabel `reviews`

Sumber: `database/migrations/2026_01_01_000003_create_reviews_table.php:12-24`.

| Kolom | Tipe SQL | Null | Default |
|---|---|---|---|
| id | bigint | tidak | sequence |
| book_id | bigint | tidak | - |
| user_id | bigint | tidak | - |
| rating | smallint | tidak | - |
| comment | text | tidak | `''` |
| created_at / updated_at | timestamptz | ya | - |

PK: `id`. UNIQUE: `(book_id, user_id)`. FK: `book_id` ke `books(id)` CASCADE; `user_id` ke `users(id)` CASCADE. CHECK: `reviews_rating_check (rating BETWEEN 1 AND 5)`. INDEX: `reviews_created_at_index`.

### 3.5 Tabel `favorites`

Sumber: `database/migrations/2026_01_01_000004_create_favorites_table.php:11-17`.

| Kolom | Tipe SQL | Null | Default |
|---|---|---|---|
| user_id | bigint | tidak | - |
| book_id | bigint | tidak | - |
| created_at / updated_at | timestamptz | ya | - |

PK: `(user_id, book_id)` (sekaligus unique). FK: `user_id` ke `users(id)` CASCADE; `book_id` ke `books(id)` CASCADE. Tidak ada CHECK atau indeks tambahan.

### 3.6 Tabel `loans`

Sumber: `database/migrations/2026_01_01_000005_create_loans_table.php:12-29`, `2026_11_18_000001_add_fines_to_loans_and_suspend_to_users.php:12-28`, `2026_11_20_000002_add_hybrid_circulation_fields.php:12-23`, `2026_11_22_000003_add_replacement_costs.php:15-20`.

| Kolom | Tipe SQL | Null | Default |
|---|---|---|---|
| id | bigint | tidak | sequence |
| user_id | bigint | tidak | - |
| book_id | bigint | tidak | - |
| format | varchar(10) | tidak | `'digital'` |
| copy_id | bigint | ya | - |
| borrowed_at | timestamptz | tidak | `CURRENT_TIMESTAMP` |
| due_at | timestamptz | tidak | - |
| returned_at | timestamptz | ya | - |
| renewals | smallint | tidak | `0` |
| fine_rate | integer | tidak | `0` |
| late_days | smallint | tidak | `0` |
| fine_amount | integer | tidak | `0` |
| fine_paid_at | timestamptz | ya | - |
| fine_waived_at | timestamptz | ya | - |
| fine_waived_by | bigint | ya | - |
| replacement_amount | bigint | tidak | `0` |
| replacement_paid_at | timestamptz | ya | - |
| replacement_waived_at | timestamptz | ya | - |
| replacement_waived_by | bigint | ya | - |

Tidak ada kolom `created_at`/`updated_at` (`app/Models/Loan.php:23`). PK: `id`. FK: `user_id` ke `users(id)` CASCADE; `book_id` ke `books(id)` CASCADE; `copy_id` ke `book_copies(id)` SET NULL; `fine_waived_by` ke `users(id)` SET NULL; `replacement_waived_by` ke `users(id)` SET NULL. CHECK: `loans_due_check (due_at > borrowed_at)`, `loans_format_check (format IN ('digital','fisik'))`. UNIQUE parsial: `loans_one_open (user_id, book_id) WHERE returned_at IS NULL`; `loans_one_open_copy (copy_id) WHERE returned_at IS NULL AND copy_id IS NOT NULL`. INDEX: `loans_user_id_borrowed_at_index`, `loans_borrowed_at_index`, `loans_user_id_fine_paid_at_index`, `loans_unpaid_fines (user_id) WHERE fine_amount > 0 AND fine_paid_at IS NULL AND fine_waived_at IS NULL`.

### 3.7 Tabel `activity_logs`

Sumber: `database/migrations/2026_01_01_000006_create_activity_logs_table.php:11-22`.

| Kolom | Tipe SQL | Null | Default |
|---|---|---|---|
| id | bigint | tidak | sequence |
| user_id | bigint | ya | - |
| action | varchar(60) | tidak | - |
| entity_type | varchar(40) | tidak | - |
| entity_id | bigint | ya | - |
| message | text | tidak | - |
| ip | varchar(45) | ya | - |
| created_at | timestamptz | tidak | `CURRENT_TIMESTAMP` |

PK: `id`. FK: `user_id` ke `users(id)` SET NULL. `entity_id` bersifat polimorfik, **tanpa FK**. Tidak ada UNIQUE/CHECK. INDEX: `activity_logs_created_at_index`. Tidak ada `updated_at` (`app/Models/ActivityLog.php:13`).

### 3.8 Tabel `book_copies`

Sumber: `database/migrations/2026_11_20_000001_create_book_copies_table.php:12-26`, `2026_11_22_000002_rename_reserved_copy_status.php:10-15`, `2026_11_22_000003_add_replacement_costs.php:11-13`.

| Kolom | Tipe SQL | Null | Default |
|---|---|---|---|
| id | bigint | tidak | sequence |
| book_id | bigint | tidak | - |
| code | varchar(40) | tidak | - |
| location | varchar(80) | ya | - |
| replacement_cost | bigint | ya | - |
| status | varchar(20) | tidak | `'available'` |
| created_at / updated_at | timestamptz | ya | - |

PK: `id`. UNIQUE: `code`. FK: `book_id` ke `books(id)` CASCADE. CHECK: `book_copies_status_check (status IN ('available','loan','on_hold','lost','damaged','withdrawn'))`. INDEX: `book_copies_book_id_status_index (book_id, status)`.

### 3.9 Tabel `holds`

Sumber: `database/migrations/2026_11_22_000001_create_holds_table.php:12-35`.

| Kolom | Tipe SQL | Null | Default |
|---|---|---|---|
| id | bigint | tidak | sequence |
| user_id | bigint | tidak | - |
| book_id | bigint | tidak | - |
| copy_id | bigint | ya | - |
| loan_id | bigint | ya | - |
| status | varchar(20) | tidak | `'aktif'` |
| ready_at | timestamptz | ya | - |
| pickup_expires_at | timestamptz | ya | - |
| fulfilled_at | timestamptz | ya | - |
| created_at / updated_at | timestamptz | ya | - |

PK: `id`. FK: `user_id` ke `users(id)` CASCADE; `book_id` ke `books(id)` CASCADE; `copy_id` ke `book_copies(id)` SET NULL; `loan_id` ke `loans(id)` SET NULL. CHECK: `holds_status_check (status IN ('aktif','siap_diambil','dipenuhi','kedaluwarsa','dibatalkan'))`. UNIQUE parsial: `holds_one_pending (user_id, book_id) WHERE status IN ('aktif','siap_diambil')`. INDEX: `holds_book_id_status_index`, `holds_user_id_status_index`.

### 3.10 Kardinalitas relasi

| Relasi | Kardinalitas | Bukti |
|---|---|---|
| `categories` ke `books` | 1:N, `books.category_id` nullable, RESTRICT | `database/migrations/2026_01_01_000002_create_books_table.php:22` |
| `users` ke `books` lewat `favorites` | N:N, PK komposit | `database/migrations/2026_01_01_000004_create_favorites_table.php:12-16` |
| `users` ke `reviews` | 1:N, CASCADE | `database/migrations/2026_01_01_000003_create_reviews_table.php:15` |
| `books` ke `reviews` | 1:N, CASCADE, unik per pasangan | `database/migrations/2026_01_01_000003_create_reviews_table.php:14,20` |
| `users` ke `loans` | 1:N, CASCADE | `database/migrations/2026_01_01_000005_create_loans_table.php:14` |
| `books` ke `loans` | 1:N, CASCADE | `database/migrations/2026_01_01_000005_create_loans_table.php:15` |
| `book_copies` ke `loans` | 1:N, SET NULL, maks satu open per copy | `database/migrations/2026_11_20_000002_add_hybrid_circulation_fields.php:14,20-23` |
| `books` ke `book_copies` | 1:N, CASCADE, `code` unik | `database/migrations/2026_11_20_000001_create_book_copies_table.php:14-15` |
| `users` ke `holds` | 1:N, CASCADE | `database/migrations/2026_11_22_000001_create_holds_table.php:14` |
| `books` ke `holds` | 1:N, CASCADE | `database/migrations/2026_11_22_000001_create_holds_table.php:15` |
| `book_copies` ke `holds` | 1:N, SET NULL | `database/migrations/2026_11_22_000001_create_holds_table.php:16` |
| `loans` ke `holds` | 1:N, SET NULL | `database/migrations/2026_11_22_000001_create_holds_table.php:17` |
| `users` ke `activity_logs` | 1:N, SET NULL, `user_id` nullable | `database/migrations/2026_01_01_000006_create_activity_logs_table.php:13` |
| `users` ke `loans` sebagai waivers | 1:N lewat `fine_waived_by` dan `replacement_waived_by`, SET NULL | `2026_11_18_000001...php:19`, `2026_11_22_000003...php:19` |

Relasi inverse `HasMany` dari `User`, `Book`, `BookCopy`, dan `Loan` ke `Hold`: **TIDAK DITEMUKAN**.

---

## 4. SKRIP SQL

### 4.1 DDL lengkap (PostgreSQL)

```sql
CREATE TABLE public.users (
    id bigserial PRIMARY KEY,
    name varchar(120) NOT NULL,
    email varchar(190) NOT NULL,
    password varchar(255) NOT NULL,
    role varchar(10) DEFAULT 'member' NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    must_change_password boolean DEFAULT false NOT NULL,
    last_login_at timestamptz,
    remember_token varchar(100),
    created_at timestamptz,
    updated_at timestamptz,
    suspend_until timestamptz,
    member_number varchar(30),
    membership_expires_at timestamptz,
    CONSTRAINT users_email_unique UNIQUE (email),
    CONSTRAINT users_member_number_unique UNIQUE (member_number),
    CONSTRAINT users_role_check CHECK (role IN ('member', 'admin'))
);
CREATE INDEX users_suspend_until_index ON public.users USING btree (suspend_until);

CREATE TABLE public.categories (
    id bigserial PRIMARY KEY,
    name varchar(80) NOT NULL,
    slug varchar(100) NOT NULL,
    created_at timestamptz,
    updated_at timestamptz,
    CONSTRAINT categories_name_unique UNIQUE (name),
    CONSTRAINT categories_slug_unique UNIQUE (slug)
);

CREATE TABLE public.books (
    id bigserial PRIMARY KEY,
    title varchar(255) NOT NULL,
    author varchar(255) NOT NULL,
    isbn varchar(20),
    publisher varchar(160),
    published_year smallint,
    language varchar(40),
    pages integer,
    call_number varchar(40),
    category_id bigint,
    description text DEFAULT '' NOT NULL,
    cover_path varchar(255),
    pdf_path varchar(255),
    created_at timestamptz,
    updated_at timestamptz,
    CONSTRAINT books_category_id_foreign FOREIGN KEY (category_id)
        REFERENCES public.categories(id) ON DELETE RESTRICT,
    CONSTRAINT books_year_check CHECK (published_year BETWEEN 1000 AND 2200),
    CONSTRAINT books_pages_check CHECK (pages > 0)
);
CREATE INDEX books_created_at_index ON public.books USING btree (created_at);
CREATE INDEX books_published_year_index ON public.books USING btree (published_year);
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX books_title_trgm ON public.books USING gin (title gin_trgm_ops);
CREATE INDEX books_author_trgm ON public.books USING gin (author gin_trgm_ops);

CREATE TABLE public.reviews (
    id bigserial PRIMARY KEY,
    book_id bigint NOT NULL,
    user_id bigint NOT NULL,
    rating smallint NOT NULL,
    comment text DEFAULT '' NOT NULL,
    created_at timestamptz,
    updated_at timestamptz,
    CONSTRAINT reviews_book_id_foreign FOREIGN KEY (book_id)
        REFERENCES public.books(id) ON DELETE CASCADE,
    CONSTRAINT reviews_user_id_foreign FOREIGN KEY (user_id)
        REFERENCES public.users(id) ON DELETE CASCADE,
    CONSTRAINT reviews_book_id_user_id_unique UNIQUE (book_id, user_id),
    CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)
);
CREATE INDEX reviews_created_at_index ON public.reviews USING btree (created_at);

CREATE TABLE public.favorites (
    user_id bigint NOT NULL,
    book_id bigint NOT NULL,
    created_at timestamptz,
    updated_at timestamptz,
    CONSTRAINT favorites_pkey PRIMARY KEY (user_id, book_id),
    CONSTRAINT favorites_user_id_foreign FOREIGN KEY (user_id)
        REFERENCES public.users(id) ON DELETE CASCADE,
    CONSTRAINT favorites_book_id_foreign FOREIGN KEY (book_id)
        REFERENCES public.books(id) ON DELETE CASCADE
);

CREATE TABLE public.activity_logs (
    id bigserial PRIMARY KEY,
    user_id bigint,
    action varchar(60) NOT NULL,
    entity_type varchar(40) NOT NULL,
    entity_id bigint,
    message text NOT NULL,
    ip varchar(45),
    created_at timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT activity_logs_user_id_foreign FOREIGN KEY (user_id)
        REFERENCES public.users(id) ON DELETE SET NULL
);
CREATE INDEX activity_logs_created_at_index ON public.activity_logs USING btree (created_at);

CREATE TABLE public.book_copies (
    id bigserial PRIMARY KEY,
    book_id bigint NOT NULL,
    code varchar(40) NOT NULL,
    location varchar(80),
    replacement_cost bigint,
    status varchar(20) DEFAULT 'available' NOT NULL,
    created_at timestamptz,
    updated_at timestamptz,
    CONSTRAINT book_copies_code_unique UNIQUE (code),
    CONSTRAINT book_copies_book_id_foreign FOREIGN KEY (book_id)
        REFERENCES public.books(id) ON DELETE CASCADE,
    CONSTRAINT book_copies_status_check CHECK (
        status IN ('available','loan','on_hold','lost','damaged','withdrawn')
    )
);
CREATE INDEX book_copies_book_id_status_index
    ON public.book_copies USING btree (book_id, status);

CREATE TABLE public.loans (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL,
    book_id bigint NOT NULL,
    format varchar(10) DEFAULT 'digital' NOT NULL,
    copy_id bigint,
    borrowed_at timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
    due_at timestamptz NOT NULL,
    returned_at timestamptz,
    renewals smallint DEFAULT 0 NOT NULL,
    fine_rate integer DEFAULT 0 NOT NULL,
    late_days smallint DEFAULT 0 NOT NULL,
    fine_amount integer DEFAULT 0 NOT NULL,
    fine_paid_at timestamptz,
    fine_waived_at timestamptz,
    fine_waived_by bigint,
    replacement_amount bigint DEFAULT 0 NOT NULL,
    replacement_paid_at timestamptz,
    replacement_waived_at timestamptz,
    replacement_waived_by bigint,
    CONSTRAINT loans_user_id_foreign FOREIGN KEY (user_id)
        REFERENCES public.users(id) ON DELETE CASCADE,
    CONSTRAINT loans_book_id_foreign FOREIGN KEY (book_id)
        REFERENCES public.books(id) ON DELETE CASCADE,
    CONSTRAINT loans_copy_id_foreign FOREIGN KEY (copy_id)
        REFERENCES public.book_copies(id) ON DELETE SET NULL,
    CONSTRAINT loans_fine_waived_by_foreign FOREIGN KEY (fine_waived_by)
        REFERENCES public.users(id) ON DELETE SET NULL,
    CONSTRAINT loans_replacement_waived_by_foreign FOREIGN KEY (replacement_waived_by)
        REFERENCES public.users(id) ON DELETE SET NULL,
    CONSTRAINT loans_due_check CHECK (due_at > borrowed_at),
    CONSTRAINT loans_format_check CHECK (format IN ('digital','fisik'))
);
CREATE UNIQUE INDEX loans_one_open ON public.loans USING btree (user_id, book_id)
    WHERE returned_at IS NULL;
CREATE UNIQUE INDEX loans_one_open_copy ON public.loans USING btree (copy_id)
    WHERE returned_at IS NULL AND copy_id IS NOT NULL;
CREATE INDEX loans_user_id_borrowed_at_index ON public.loans USING btree (user_id, borrowed_at);
CREATE INDEX loans_borrowed_at_index ON public.loans USING btree (borrowed_at);
CREATE INDEX loans_user_id_fine_paid_at_index ON public.loans USING btree (user_id, fine_paid_at);
CREATE INDEX loans_unpaid_fines ON public.loans USING btree (user_id)
    WHERE fine_amount > 0 AND fine_paid_at IS NULL AND fine_waived_at IS NULL;

CREATE TABLE public.holds (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL,
    book_id bigint NOT NULL,
    copy_id bigint,
    loan_id bigint,
    status varchar(20) DEFAULT 'aktif' NOT NULL,
    ready_at timestamptz,
    pickup_expires_at timestamptz,
    fulfilled_at timestamptz,
    created_at timestamptz,
    updated_at timestamptz,
    CONSTRAINT holds_user_id_foreign FOREIGN KEY (user_id)
        REFERENCES public.users(id) ON DELETE CASCADE,
    CONSTRAINT holds_book_id_foreign FOREIGN KEY (book_id)
        REFERENCES public.books(id) ON DELETE CASCADE,
    CONSTRAINT holds_copy_id_foreign FOREIGN KEY (copy_id)
        REFERENCES public.book_copies(id) ON DELETE SET NULL,
    CONSTRAINT holds_loan_id_foreign FOREIGN KEY (loan_id)
        REFERENCES public.loans(id) ON DELETE SET NULL,
    CONSTRAINT holds_status_check CHECK (
        status IN ('aktif','siap_diambil','dipenuhi','kedaluwarsa','dibatalkan')
    )
);
CREATE UNIQUE INDEX holds_one_pending ON public.holds USING btree (user_id, book_id)
    WHERE status IN ('aktif','siap_diambil');
CREATE INDEX holds_book_id_status_index ON public.holds USING btree (book_id, status);
CREATE INDEX holds_user_id_status_index ON public.holds USING btree (user_id, status);
```

### 4.2 Contoh DML

```sql
-- Tambah kategori
INSERT INTO categories (name, slug, created_at, updated_at)
VALUES ('Biografi', 'biografi', NOW(), NOW());

-- Tambah buku
INSERT INTO books (title, author, published_year, category_id, description, created_at, updated_at)
SELECT 'Laskar Pelangi', 'Andrea Hirata', 2005, c.id, 'Novel tentang sekolah di Belitung.', NOW(), NOW()
FROM categories c WHERE c.slug = 'fiction';

-- Tambah eksemplar fisik
INSERT INTO book_copies (book_id, code, location, status, replacement_cost, created_at, updated_at)
SELECT b.id, 'BK-0008-01', 'Rak F', 'available', 50000, NOW(), NOW()
FROM books b WHERE b.title = 'Laskar Pelangi';

-- Pinjam digital (contoh id)
INSERT INTO loans (user_id, book_id, format, borrowed_at, due_at, fine_rate)
VALUES (2, 7, 'digital', NOW(), NOW() + INTERVAL '14 days', 1000);

-- Tandai denda lunas
UPDATE loans SET fine_paid_at = NOW() WHERE id = 1 AND fine_amount > 0;

-- Tandai biaya penggantian dibebaskan
UPDATE loans
SET replacement_waived_at = NOW(), replacement_waived_by = 1
WHERE id = 2 AND replacement_amount > 0;

-- Hapus favorit
DELETE FROM favorites WHERE user_id = 2 AND book_id = 3;

-- Batalkan hold
UPDATE holds SET status = 'dibatalkan' WHERE id = 1 AND status = 'aktif';
```

### 4.3 Delapan query DQL

**1. Katalog lengkap dengan rating, eksemplar tersedia, dan jumlah pinjaman**

```sql
SELECT b.id, b.title, b.author, c.name AS kategori,
       ROUND(AVG(r.rating)::numeric, 1) AS rating_rata_rata,
       COUNT(DISTINCT r.id) AS jumlah_ulasan,
       COUNT(DISTINCT bc.id) FILTER (WHERE bc.status = 'available') AS eksemplar_tersedia,
       COUNT(DISTINCT l.id) AS jumlah_dipinjam
FROM books b
LEFT JOIN categories c ON c.id = b.category_id
LEFT JOIN reviews r ON r.book_id = b.id
LEFT JOIN book_copies bc ON bc.book_id = b.id
LEFT JOIN loans l ON l.book_id = b.id
GROUP BY b.id, b.title, b.author, c.name
ORDER BY b.title;
```

**2. Pinjaman aktif beserta anggota, eksemplar, dan sisa hari**

```sql
SELECT u.name AS anggota, u.member_number, b.title, l.format,
       bc.code AS barcode, l.borrowed_at, l.due_at,
       (l.due_at::date - CURRENT_DATE) AS sisa_hari
FROM loans l
JOIN users u ON u.id = l.user_id
JOIN books b ON b.id = l.book_id
LEFT JOIN book_copies bc ON bc.id = l.copy_id
WHERE l.returned_at IS NULL
ORDER BY l.due_at;
```

**3. Antrean hold per judul dengan nomor urut**

```sql
SELECT b.title, h.id AS hold_id, u.name AS anggota, h.status,
       h.ready_at, h.pickup_expires_at,
       ROW_NUMBER() OVER (PARTITION BY h.book_id ORDER BY h.id) AS urutan
FROM holds h
JOIN books b ON b.id = h.book_id
JOIN users u ON u.id = h.user_id
WHERE h.status IN ('aktif', 'siap_diambil')
ORDER BY h.book_id, h.id;
```

**4. Tunggakan anggota dengan subquery**

```sql
SELECT u.id, u.name, u.member_number, u.suspend_until,
       (SELECT COALESCE(SUM(l.fine_amount), 0)
        FROM loans l
        WHERE l.user_id = u.id AND l.fine_amount > 0
          AND l.fine_paid_at IS NULL AND l.fine_waived_at IS NULL) AS denda,
       (SELECT COALESCE(SUM(l.replacement_amount), 0)
        FROM loans l
        WHERE l.user_id = u.id AND l.replacement_amount > 0
          AND l.replacement_paid_at IS NULL AND l.replacement_waived_at IS NULL) AS biaya_ganti
FROM users u
WHERE u.suspend_until > CURRENT_TIMESTAMP
   OR EXISTS (
        SELECT 1 FROM loans l2
        WHERE l2.user_id = u.id
          AND ((l2.fine_amount > 0 AND l2.fine_paid_at IS NULL AND l2.fine_waived_at IS NULL)
            OR (l2.replacement_amount > 0 AND l2.replacement_paid_at IS NULL
                AND l2.replacement_waived_at IS NULL))
   )
ORDER BY u.suspend_until NULLS LAST;
```

**5. Buku paling sering dipinjam 12 bulan terakhir**

```sql
SELECT b.title, COUNT(*) AS jumlah_pinjaman
FROM books b
JOIN loans l ON l.book_id = b.id
WHERE l.borrowed_at >= CURRENT_TIMESTAMP - INTERVAL '12 months'
GROUP BY b.id, b.title
ORDER BY jumlah_pinjaman DESC
LIMIT 10;
```

**6. Aktivitas petugas per hari dan jenis aksi**

```sql
SELECT DATE_TRUNC('day', al.created_at) AS hari,
       COALESCE(u.name, 'sistem') AS pelaku,
       al.action, COUNT(*) AS jumlah
FROM activity_logs al
LEFT JOIN users u ON u.id = al.user_id
GROUP BY 1, 2, 3
ORDER BY hari DESC, jumlah DESC;
```

**7. Eksemplar yang ditahan untuk hold siap diambil**

```sql
SELECT bc.code AS barcode, b.title, u.name AS pemegang_hold,
       h.ready_at, h.pickup_expires_at
FROM holds h
JOIN book_copies bc ON bc.id = h.copy_id
JOIN books b ON b.id = h.book_id
JOIN users u ON u.id = h.user_id
WHERE h.status = 'siap_diambil' AND bc.status = 'on_hold'
ORDER BY h.pickup_expires_at;
```

**8. Anggota yang memenuhi syarat memasang hold**

```sql
SELECT u.id, u.name, u.member_number
FROM users u
WHERE u.is_active
  AND u.role = 'member'
  AND (u.membership_expires_at IS NULL OR u.membership_expires_at > CURRENT_TIMESTAMP)
  AND (u.suspend_until IS NULL OR u.suspend_until <= CURRENT_TIMESTAMP)
  AND NOT EXISTS (
        SELECT 1 FROM loans l
        WHERE l.user_id = u.id AND l.fine_amount > 0
          AND l.fine_paid_at IS NULL AND l.fine_waived_at IS NULL
  )
  AND (SELECT COUNT(*) FROM loans l2
       WHERE l2.user_id = u.id AND l2.returned_at IS NULL) < 5
ORDER BY u.name;
```

### 4.4 Kueri untuk menguji constraint

**Peminjaman ganda untuk anggota dan buku yang sama.** Jalankan dua kali; eksekusi kedua harus gagal dengan pelanggaran `loans_one_open`.

```sql
BEGIN;
INSERT INTO loans (user_id, book_id, format, borrowed_at, due_at, fine_rate)
VALUES (2, 3, 'digital', NOW(), NOW() + INTERVAL '14 days', 1000);
INSERT INTO loans (user_id, book_id, format, borrowed_at, due_at, fine_rate)
VALUES (2, 3, 'digital', NOW(), NOW() + INTERVAL '14 days', 1000);
ROLLBACK;
```

Error yang diharapkan: `duplicate key value violates unique constraint "loans_one_open"`.

**Rating di luar rentang 1 sampai 5.** Harus gagal dengan pelanggaran `reviews_rating_check`.

```sql
BEGIN;
INSERT INTO reviews (book_id, user_id, rating, comment)
VALUES (1, 2, 6, 'rating di luar batas');
ROLLBACK;
```

Error yang diharapkan: `new row for relation "reviews" violates check constraint "reviews_rating_check"`.

---

## 5. DATA SEED

Sumber: `database/seeders/DatabaseSeeder.php:13-21`.

| Seeder | Isi | Sumber |
|---|---|---|
| `CategorySeeder` | 7 kategori: Programming, Fiction, History, Design, Science, Adventure, Comedy | `database/seeders/CategorySeeder.php:10-15` |
| `UserSeeder` | 2 akun demo: `admin@example.com` / `admin123` (Admin User, admin) dan `user@example.com` / `password123` (Regular User, member). Keduanya mendapat nomor anggota otomatis | `database/seeders/UserSeeder.php:12-29` |
| `BookSeeder` | 7 buku: The Pragmatic Programmer, Clean Code, 1984, To Kill a Mockingbird, Sapiens, The Design of Everyday Things, The Mystery of Cabin Island | `database/seeders/BookSeeder.php:13-86` |
| `BookCopySeeder` | 10 eksemplar, 2 per buku untuk 5 buku pertama, kode `BK-0001-01` sampai `BK-0005-02`, lokasi `Rak A` sampai `Rak E`, status `available`, `replacement_cost = 50000` | `database/seeders/BookCopySeeder.php:13-28` |
| `ReaderActivitySeeder` | 1 pinjaman, 1 ulasan rating 5, 1 favorit untuk `user@example.com` pada The Mystery of Cabin Island | `database/seeders/ReaderActivitySeeder.php:14-35` |

Buku The Mystery of Cabin Island mendapat PDF contoh dari `database/seeders/files/the-mystery-of-cabin-island.pdf`, disimpan di disk privat sebagai `books/b_<16 karakter acak>.pdf` (`database/seeders/BookSeeder.php:80-85`).

Akun demo harus diganti atau dihapus sebelum produksi (`README.md:38-39`, `README.md:200-201`).

---

## 6. ALUR KERJA PER FITUR

### 6.1 Registrasi

1. Guest membuka `GET /daftar` → `Auth\RegisterController@create` (`routes/web.php:28`, `app/Http/Controllers/Auth/RegisterController.php:18`).
2. Form mengirim `POST /daftar` → `RegisterController@store` (`:23`), middleware `throttle:10,1` (`routes/web.php:29`).
3. Validasi `RegisterRequest` (`app/Http/Requests/Auth/RegisterRequest.php:25-35`).
4. `User::create` dengan role member; nomor anggota diisi `User::memberNumberFor($id)` (`RegisterController.php:25-29`, `app/Models/User.php:54-57`).
5. `last_login_at` diisi, `Auth::login` (`:31-33`), audit `register` (`:34`).
6. Redirect ke beranda dengan flash sukses (`:36`).

### 6.2 Login dan logout

1. `GET /masuk` → `Auth\LoginController@create`; parameter `?next=` hanya path internal diterima (`routes/web.php:26`, `LoginController.php:14-22`).
2. `POST /masuk` → `LoginRequest@authenticate` (`app/Http/Requests/Auth/LoginRequest.php:41-65`): throttle, `Hash::check`, rehash, pesan seragam.
3. Sesi diregenerasi (`LoginController.php:28`). Bila `must_change_password`, redirect ke `/profil#sandi` (`:31-34`), lain ke dasbor admin atau beranda (`:36-37`).
4. `POST /keluar` → `LoginController@destroy`; logout, invalidate, regenerate token (`routes/web.php:32`, `LoginController.php:40-47`).

### 6.3 Pinjam buku digital

1. Anggota membuka `GET /buku/{book}` → `BookController@show` (`routes/web.php:22`, `app/Http/Controllers/BookController.php:20-57`).
2. Tombol "Pinjam buku" mengirim `POST /buku/{book}/pinjam` → `LoanController@store` (`routes/web.php:52`).
3. `LoanService::borrow` (`app/Services/LoanService.php:33-62`): kunci user, `BorrowEligibility::forBook`, tolak blok pertama, buat `Loan` format digital, `due_at = now()+14 hari`, `fine_rate` snapshot, audit `borrow_loan`.
4. Flash sukses dan halaman detail diperbarui (`app/Http/Controllers/LoanController.php:16-28`).

### 6.4 Baca PDF

1. Dari halaman buku atau rak pinjaman, `GET /baca/{book}` → `ReaderController@show` (`routes/web.php:43`).
2. `show` memeriksa `abort_unless($book->has_pdf, 404)` dan `cannot('read', $book)` (`app/Http/Controllers/ReaderController.php:24-28`).
3. `BookPolicy::read` menolak buku tanpa PDF, mengizinkan admin, dan mengizinkan anggota hanya bila `LoanAccess::activeFor()` tidak null (`app/Policies/BookPolicy.php:14-21`).
4. Halaman pembaca memuat `GET /berkas/{book}` → `ReaderController@file` (`routes/web.php:44`) via iframe.
5. `file` memeriksa policy, mengembalikan `BinaryFileResponse` dengan `Content-Disposition: inline`, `Cache-Control: private`, dan CSP khusus (`ReaderController.php:41-53`).

### 6.5 Perpanjang

**Digital**
1. Tombol `PATCH /buku/{book}/pinjam` (`routes/web.php:53`) → `LoanController@update` → `LoanService::renew` (`app/Services/LoanService.php:230-261`).
2. Cek: pinjaman aktif, bukan fisik, tanpa hold pending, `renewals < 1`.
3. `due_at = due_at lama + 14 hari`, `renewals++`, audit `renew_loan`.
4. Flash hasil (`app/Http/Controllers/LoanController.php:30-39`).

**Fisik**
1. Tombol admin `PATCH /admin/peminjaman/{loan}/perpanjang` (`routes/web.php:80`) → `Admin\LoanController@renew` → `LoanService::renewByStaff` (`app/Services/LoanService.php:264-294`).
2. Cek: pinjaman fisik belum kembali, tanpa hold pending, batas renewals.
3. `due_at = now()+physical_days`, `renewals++`, audit.

### 6.6 Kembalikan / check-in

**Digital**
1. `DELETE /buku/{book}/pinjam` (`routes/web.php:54`) → `LoanController@destroy` → `LoanService::giveBack` (`app/Services/LoanService.php:136-155`).
2. `closeLoan` menghitung denda dan skorsing (`app/Services/LoanService.php:368-383`, `app/Services/FineService.php:26-42`).
3. `LoanClosure::memberMessage` menjadi flash (`app/Support/LoanClosure.php:20-38`).

**Fisik**
1. Petugas membuka `GET /admin/sirkulasi` → `Admin\CirculationController@index` (`routes/web.php:74`).
2. Form check-in: barcode + kondisi wajib (`resources/views/admin/circulation.blade.php:28-44`).
3. `POST /admin/sirkulasi/kembali` → `CirculationController@checkIn` (`routes/web.php:76`, `app/Http/Controllers/Admin/CirculationController.php:72-99`).
4. Cari `BookCopy` by `code`, cari `Loan` dengan `copy_id` dan `returned_at IS NULL`, panggil `LoanService::checkInCopy` (`app/Services/LoanService.php:200-225`).
5. `closeLoan` mengubah status eksemplar sesuai kondisi, menagih biaya ganti bila rusak/hilang, atau menahan eksemplar untuk hold bila baik (`app/Services/LoanService.php:368-383`, `app/Services/HoldService.php:123-141`).

### 6.7 Ulasan

1. Halaman buku menampilkan form hanya bila `canReview` (`resources/views/books/show.blade.php:131-152`, `app/Http/Controllers/BookController.php:35-38`).
2. `PUT /buku/{book}/ulasan` → `ReviewController@store` (`routes/web.php:62`).
3. `StoreReviewRequest` membatasi rating 1-5 dan komentar maks 1000 (`app/Http/Requests/StoreReviewRequest.php:9-23`).
4. `ReviewController` memeriksa `hasBorrowed` bila config aktif, lalu `updateOrCreate` (`app/Http/Controllers/ReviewController.php:21-35`).
5. `DELETE /ulasan/{review}` → `ReviewController@destroy`, otorisasi `ReviewPolicy::delete` (`routes/web.php:45`, `ReviewController.php:38-49`).
6. Admin: `GET /admin/ulasan` dan `DELETE /admin/ulasan/{review}` (`routes/web.php:86-87`, `Admin\ReviewController.php:17-42`).

### 6.8 Favorit

1. Tombol di halaman buku (`resources/views/books/_favorite.blade.php:2-4`).
2. `PUT /buku/{book}/favorit` → `FavoriteController@store` → `syncWithoutDetaching` (`routes/web.php:59`, `app/Http/Controllers/FavoriteController.php:11-16`).
3. `DELETE /buku/{book}/favorit` → `FavoriteController@destroy` → `detach` (`routes/web.php:60`, `FavoriteController.php:18-23`).
4. Rak favorit: `GET /favorit` → `Account\FavoriteController@index` (`routes/web.php:50`).

### 6.9 CRUD admin dan pengelolaan lain

| Alur | Rute | Controller/method |
|---|---|---|
| Buku: index, baru, ubah, simpan, hapus | `/admin/buku...` | `Admin\BookController@index/create/edit/store/update/destroy` (`app/Http/Controllers/Admin/BookController.php:25,39,44,64,72,92`) |
| Eksemplar: index, baru, ubah, simpan, hapus | `/admin/eksemplar...` | `Admin\BookCopyController@index/create/edit/store/update/destroy` (`app/Http/Controllers/Admin/BookCopyController.php:21,41,59,51,69,82`) |
| Kategori: index, simpan, ubah, hapus | `/admin/kategori...` | `Admin\CategoryController@index/store/update/destroy` (`app/Http/Controllers/Admin/CategoryController.php:16,24,32,41`) |
| Check-out/check-in fisik | `/admin/sirkulasi...` | `Admin\CirculationController@index/store/checkIn` (`app/Http/Controllers/Admin/CirculationController.php:25,38,72`) |
| Peminjaman, denda, biaya ganti | `/admin/peminjaman...` | `Admin\LoanController@index/update/renew`, `Admin\LoanFineController@pay/waive/payReplacement/waiveReplacement` |
| Pengguna | `/admin/pengguna...` | `UserController@index`, `UserStatusController@update`, `UserRoleController@update`, `MembershipController@edit/update`, `UserPasswordResetController@store` |
| Log aktivitas | `/admin/log` | `Admin\ActivityLogController@index` (`app/Http/Controllers/Admin/ActivityLogController.php:13`) |

Tambah pengguna oleh admin: **TIDAK DITEMUKAN**. Pengguna baru dibuat lewat registrasi publik atau command `library:create-admin` (`app/Console/Commands/CreateAdmin.php:14`).

---

## 7. KEAMANAN

### Autentikasi

- Guard `web` memakai driver session dan provider Eloquent `App\Models\User` (`config/auth.php:18-21,40-45,64-68`).
- Sesi: database, 120 menit, cookie `http_only=true`, `same_site=lax` (`.env.example:31-35`, `config/session.php:21,35,50,185,202,231`).
- Sandi di-hash otomatis lewat cast `hashed` (`app/Models/User.php:25-35`) dengan `BCRYPT_ROUNDS=12` (`.env.example:16`), diuji `4` (`phpunit.xml:23`), dan di-rehash saat login (`app/Http/Requests/Auth/LoginRequest.php:61-63`).
- Throttle login 5 per email+IP dan 20 per IP, kunci 15 menit (`config/library.php:62-67`, `LoginRequest.php:59-105`). Email yang tidak ada tetap menjalankan `Hash::check` pada dummy hash agar tidak terdeteksi dari waktu respons (`LoginRequest.php:46-48,92-95`).
- Registrasi di-throttle 10 per menit (`routes/web.php:29`).
- Regenerasi sesi saat login/daftar/ganti sandi; invalidate + regenerate token saat logout (`LoginController.php:28,42-44`, `RegisterController.php:33`, `Account/PasswordController.php:17`).
- Open redirect dicegah: `?next=` hanya path internal (`LoginController.php:16-20`).
- Akun nonaktif langsung logout lewat `EnsureUserIsActive` (`app/Http/Middleware/EnsureUserIsActive.php:13-22`).
- Sandi sementara memaksa ganti lewat `RequirePasswordChange` (`app/Http/Middleware/RequirePasswordChange.php:12-19`).

### Otorisasi

- Gate `admin` dan `member` di `app/Providers/AppServiceProvider.php:35-36`, dipasang sebagai `can:admin` dan `can:member` di `routes/web.php:48,66`.
- `BookPolicy::read` membatasi PDF (`app/Policies/BookPolicy.php:14-21`).
- `ReviewPolicy::delete` membatasi penghapusan ulasan (`app/Policies/ReviewPolicy.php:10-13`).
- FormRequest admin menolak non-admin (`app/Http/Requests/Admin/BookRequest.php:11-14`, `BookCopyRequest.php:11-14`, `CategoryRequest.php:11-13`).
- Admin tidak bisa menonaktifkan, menurunkan peran, atau mereset sandi diri sendiri, dan tidak bisa menonaktifkan admin aktif terakhir (`Admin/UserStatusController.php:19-26`, `UserRoleController.php:25-27`, `UserPasswordResetController.php:19-21`).
- Pendaftaran policy secara manual: **TIDAK DITEMUKAN** (auto-discovery Laravel).

### Akses file PDF

- Disk `local` privat, root `storage/app/private`, `serve=false` (`config/filesystems.php:33-39`).
- Nama file `books/b_<acak>.pdf` (`app/Services/BookMedia.php:66-71`).
- Rute `auth + password.fresh` (`routes/web.php:43-44`).
- Pemeriksaan: buku harus punya PDF, policy `read` harus lolos, file harus ada (`ReaderController.php:24,43-45`).
- Respons memakai `BinaryFileResponse`, mendukung range request, `Content-Disposition: inline`, `Cache-Control: private, max-age=0, must-revalidate`, CSP `frame-ancestors 'self'` (`ReaderController.php:37-52`).
- Admin diizinkan membaca semua PDF (`app/Policies/BookPolicy.php:20`).

### Validasi upload

- Sampul: `nullable|file|image|mimes:jpg,jpeg,png,webp|max:3072` (`app/Http/Requests/Admin/BookRequest.php:30`).
- PDF: `nullable|file|mimetypes:application/pdf|max:51200|new PdfSignature` (`BookRequest.php:31`).
- Tanda tangan PDF: 5 byte pertama harus `%PDF-` (`app/Rules/PdfSignature.php:15-22`).
- Gambar diperiksa dengan `getimagesize`, resolusi lebih dari 40 megapiksel ditolak (`app/Services/BookMedia.php:25-32`).
- Sampul di-encode ulang ke JPEG kualitas 85 dan di-scale ke 800×1200 (`BookMedia.php:43-61`). Bila GD tidak ada, berkas disimpan apa adanya setelah lolos validasi `image` (`BookMedia.php:36-41`).
- Sampul di disk publik `storage/app/public/covers`, PDF di disk privat `storage/app/private/books` (`config/filesystems.php:33-49`).
- Pesan khusus bila `upload_max_filesize` PHP terlampaui (`BookRequest.php:52-64`, `bootstrap/app.php:40-43`).

### Activity log dan header

- Penulis tunggal `Audit::record()` (`app/Services/Audit.php:191-201`). Kolom: `user_id`, `action`, `entity_type`, `entity_id`, `message`, `ip`, `created_at`. Tidak ada kolom metadata JSON (**TIDAK DITEMUKAN**).
- 32 aksi tercatat, daftar di `app/Enums/ActivityAction.php:9-42`; detail pemanggil ada di `app/Services/Audit.php:25-189`.
- Header keamanan global: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP, dan HSTS bila HTTPS (`app/Http/Middleware/SecurityHeaders.php:17-31`). CSP tidak mengizinkan host eksternal (`SecurityHeaders.php:36-53`).
- CSRF bawaan Laravel tetap aktif; konfigurasi `VerifyCsrfToken::except`: **TIDAK DITEMUKAN**.

---

## 8. TEKNOLOGI

### Versi

| Komponen | Persyaratan | Versi terpasang saat pemeriksaan | Sumber |
|---|---|---|---|
| PHP | `^8.3` | 8.5.13 | `composer.json:14`, `php -v` |
| Laravel | `^13.17` | 13.37.0 | `composer.json:15`, `php artisan --version` |
| PostgreSQL | 13+ | 18.7 | `README.md:8`, `psql --version` |
| Node.js | 20.19+ / 22.12+ | 26.13.1 | `README.md:10`, `node -v` |
| Composer | 2 | 2.11.3 | `README.md:9`, `composer --version` |
| npm | TIDAK DITEMUKAN di README | 11.20.0 | `npm -v` |

### Library utama

- Produksi: `laravel/framework`, `laravel/tinker` (`composer.json:13-17`).
- Pengembangan: `fakerphp/faker`, `laravel/pail`, `laravel/pao`, `laravel/pint`, `mockery/mockery`, `nunomaduro/collision`, `phpunit/phpunit` (`composer.json:18-26`).
- Frontend: Vite 8, `laravel-vite-plugin` 3.1, `@fontsource-variable/literata`, `@fontsource/atkinson-hyperlegible`, opsional `@laravel/multiplex` (`package.json:9-17`).
- Database dan Docker: `docker-compose.yml:7-24` memakai `postgres:16-alpine`, port host 5433, user `perpus`, database `perpustakaan_laravel`.
- Pengujian: PHPUnit 12.5, database `perpustakaan_testing`, `Storage::fake` (`phpunit.xml:20-35`, `tests/TestCase.php:11-18`).
- Kualitas kode: Pint, `Model::shouldBeStrict()` di luar produksi (`app/Providers/AppServiceProvider.php:24-28`).

### Cara menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
docker compose up -d
php artisan migrate --seed
php artisan storage:link
php artisan library:create-admin
php artisan serve
```

Sumber: `README.md:14-27`. Batas unggah PHP harus diubah menjadi `upload_max_filesize=60M` dan `post_max_size=64M` (`README.md:41-51`). Aset dibangun dengan `npm install && npm run build` (`README.md:53-61`).

Tes: `php artisan test` atau `vendor/bin/phpunit`; Pint: `vendor/bin/pint` (`README.md:165-178`). Composer scripts: `composer setup`, `composer dev`, `composer test` (`composer.json:39-55`).

Produksi: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `composer install --no-dev --optimize-autoloader`, `npm install && npm run build`, `php artisan migrate --force`, `php artisan storage:link`, `php artisan optimize`, document root `public/` (`README.md:180-192`). Cron: `* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1` (`README.md:194-200`).

---

## 9. PENGUJIAN

### Hasil eksekusi

Perintah `php artisan test` dijalankan pada repositori ini menghasilkan:

```
Tests:  239 passed
Assertions: 797
Duration: approximately 34 seconds
```

Status Pint juga bersih saat pemeriksaan.

### Daftar test otomatis

Berikut seluruh method tes yang ditemukan, dikelompokkan per berkas.

**`tests/Feature/AccountTest.php`**
- `:13 test_name_can_be_changed_without_a_password`: nama bisa diubah tanpa konfirmasi sandi.
- `:22 test_changing_email_requires_the_current_password`: email ditolak tanpa sandi benar dan tersimpan lowercase.
- `:34 test_email_must_be_unique_among_other_users`: email yang dipakai user lain ditolak.
- `:43 test_password_change_requires_the_current_password_and_a_new_one`: sandi salah/sama/pendek ditolak.
- `:60 test_admin_profile_page_has_no_member_tabs`: profil admin tanpa tab anggota.

**`tests/Feature/Admin/AccessTest.php`**
- `:15 test_members_get_403_on_every_admin_page`: anggota mendapat 403 di semua halaman admin.
- `:24 test_guests_are_redirected_to_login`: guest dialihkan ke login.
- `:31 test_admins_can_open_every_admin_page`: admin mendapat 200 di semua halaman admin.
- `:40 test_dashboard_shows_the_figures`: statistik dan grafik ditampilkan.

**`tests/Feature/Admin/BookManagementTest.php`**
- `:30 test_admin_creates_a_book_with_cover_and_pdf`: buku, PDF privat, sampul JPEG, log `create_book`.
- `:44 test_cover_urls_are_relative_so_they_work_on_any_host_and_port`: URL sampul relatif `/storage`.
- `:54 test_pdf_is_stored_on_the_private_disk_not_the_public_one`: PDF tidak masuk disk publik.
- `:63 test_a_php_file_disguised_as_an_image_is_rejected`: file PHP menyamar sebagai gambar ditolak.
- `:73 test_svg_and_non_pdf_files_are_rejected`: SVG dan PDF palsu ditolak.
- `:86 test_file_size_limits_are_enforced`: batas ukuran cover dan PDF ditegakkan.
- `:96 test_validation_messages_for_the_book_form`: pesan validasi terlokalisi.
- `:106 test_titles_with_html_are_escaped_on_the_book_page`: HTML pada judul di-escape.
- `:113 test_updating_replaces_files_and_removes_the_old_ones`: berkas lama dihapus saat update.
- `:131 test_files_can_be_removed_and_are_kept_when_nothing_is_uploaded`: flag remove dan berkas tanpa unggahan.
- `:148 test_deleting_a_book_deletes_its_files`: hapus buku ikut menghapus berkas.
- `:161 test_book_list_supports_search_and_category_filter`: pencarian dan filter kategori admin.
- `:171 test_the_edit_form_uses_localised_urls`: URL edit memakai `/ubah`.

**`tests/Feature/Admin/ManagementTest.php`**
- `:21 test_categories_can_be_created_renamed_and_deleted`: CRUD kategori dan slug.
- `:36 test_category_names_and_slugs_are_unique`: nama duplikat ditolak, slug bentrok diberi sufiks.
- `:48 test_a_category_in_use_cannot_be_deleted`: kategori dipakai buku tidak bisa dihapus.
- `:58 test_admin_can_end_an_active_loan`: admin mengakhiri pinjaman dan log `finish_loan`.
- `:69 test_ending_an_already_finished_loan_reports_an_error`: pinjaman selesai tidak bisa diakhiri ulang.
- `:76 test_loan_list_filters_by_status_and_search`: filter status dan pencarian.
- `:92 test_admin_can_moderate_reviews`: pencarian dan penghapusan ulasan.
- `:104 test_admin_resets_a_password_and_the_user_must_change_it`: reset sandi dan paksa ganti.
- `:124 test_admin_cannot_reset_deactivate_or_change_the_role_of_themselves`: admin tidak bisa mengubah diri sendiri.
- `:136 test_the_last_active_admin_cannot_be_deactivated`: admin aktif terakhir dilindungi.
- `:154 test_admin_can_deactivate_activate_and_change_roles`: status dan peran bisa diubah.
- `:170 test_user_list_filters_by_search_and_role`: filter pengguna.
- `:181 test_search_wildcards_are_treated_as_letters`: wildcard dibaca literal.
- `:192 test_activity_log_lists_and_searches_entries`: daftar dan pencarian log.

**`tests/Feature/AuditTest.php`**
- `:25 test_borrowing_is_logged_with_the_actor_and_the_loan`: log `borrow_loan`.
- `:40 test_renewing_is_logged`: log `renew_loan`.
- `:54 test_returning_is_logged`: log `return_loan`.
- `:69 test_a_late_return_logs_the_return_and_the_fine`: log `return_loan` dan `fine_assessed`.
- `:80 test_an_admin_ending_a_loan_logs_the_end_and_the_fine`: log `finish_loan` dan denda.

**`tests/Feature/AuthTest.php`**
- `:13 test_login_and_register_pages_render_for_guests`: halaman auth tampil.
- `:19 test_protected_pages_redirect_guests_to_login`: rute terproteksi mengalihkan guest.
- `:26 test_registration_creates_a_member_and_logs_in`: registrasi dan login otomatis.
- `:39 test_registration_validates_input`: validasi registrasi.
- `:50 test_duplicate_email_is_rejected_regardless_of_case`: duplikat email ditolak.
- `:58 test_user_names_are_escaped_in_the_interface`: HTML pada nama di-escape.
- `:65 test_members_and_admins_are_sent_to_their_own_home`: redirect sesuai peran.
- `:77 test_wrong_credentials_and_inactive_accounts_cannot_log_in`: kredensial salah dan akun nonaktif ditolak.
- `:88 test_login_is_throttled_after_repeated_failures`: throttle login.
- `:101 test_next_parameter_only_accepts_internal_paths`: parameter `next` dibatasi.
- `:115 test_logout_requires_post_and_ends_the_session`: logout wajib POST.
- `:122 test_deactivated_accounts_are_logged_out_immediately`: akun nonaktif langsung keluar.
- `:133 test_temporary_password_forces_a_password_change`: sandi sementara memaksa ganti.

**`tests/Feature/BorrowEligibilityTest.php`**
- `:32 test_a_clear_member_has_no_blocks`: anggota bersih tidak diblokir.
- `:42 test_an_inactive_account_cannot_borrow`: akun nonaktif diblokir.
- `:53 test_an_admin_cannot_borrow_as_a_member`: admin diblokir.
- `:63 test_account_problems_are_reported_in_order`: urutan blok.
- `:75 test_a_block_carries_the_data_its_message_needs`: data blok lengkap.
- `:88 test_overdue_titles_are_limited_to_two`: judul telat dibatasi dua.
- `:98 test_a_book_without_a_pdf_comes_first`: blok tanpa PDF didahulukan.
- `:108 test_an_open_loan_on_the_same_book_blocks_a_second_copy`: pinjaman terbuka memblokir buku sama.
- `:120 test_reaching_the_quota_blocks_another_book`: kuota penuh memblokir.
- `:135 test_borrow_flashes_only_the_first_reason`: hanya alasan pertama ditampilkan saat pinjam.
- `:146 test_the_shelf_lists_every_reason`: rak menampilkan semua alasan.

**`tests/Feature/CatalogTest.php`**
- `:15 test_home_and_category_pages_render`: beranda dan kategori tampil.
- `:24 test_empty_catalog_renders`: katalog kosong.
- `:30 test_search_is_case_insensitive_and_matches_author_and_isbn`: pencarian case-insensitive.
- `:40 test_like_wildcards_in_search_are_literal`: wildcard literal.
- `:48 test_filters_by_category_year_language_and_format`: filter katalog.
- `:62 test_every_sort_option_works`: semua opsi urutan.
- `:72 test_rating_sort_puts_best_rated_first`: urutan rating.
- `:82 test_odd_query_parameters_never_cause_errors`: parameter aneh aman.
- `:91 test_pagination_uses_the_halaman_parameter`: paginasi `halaman`.
- `:99 test_active_filters_are_shown_as_removable_chips`: chip filter.

**`tests/Feature/CheckInTest.php`**
- `:32 test_a_copy_can_be_checked_in_by_barcode`: check-in sukses.
- `:46 test_a_damaged_copy_is_not_put_back_on_the_shelf_or_on_hold`: eksemplar rusak tidak masuk rak/hold.
- `:60 test_the_check_in_form_has_no_default_condition`: form tidak punya kondisi default.
- `:67 test_an_unknown_barcode_is_rejected`: barcode tidak dikenal ditolak.

**`tests/Feature/CopyManagementTest.php`**
- `:23 test_an_admin_can_add_a_copy_with_a_replacement_cost`: tambah eksemplar dan biaya ganti.
- `:39 test_two_copies_cannot_share_a_barcode`: barcode duplikat ditolak.
- `:51 test_an_admin_can_change_a_copy`: ubah eksemplar.
- `:71 test_a_copy_on_loan_cannot_be_deleted`: eksemplar dipinjam tidak bisa dihapus.
- `:87 test_a_copy_on_loan_cannot_be_marked_available`: eksemplar dipinjam tidak bisa diset tersedia.
- `:107 test_a_copy_reserved_for_a_hold_cannot_be_deleted`: eksemplar ditahan hold tidak bisa dihapus.
- `:126 test_a_damaged_return_charges_the_replacement_cost_and_blocks_borrowing`: biaya ganti dan blokir.
- `:151 test_paying_the_replacement_cost_restores_borrowing`: pelunasan memulihkan pinjam.
- `:170 test_a_replacement_cost_can_be_waived_with_a_reason`: pembebasan dengan alasan.

**`tests/Feature/CreateAdminCommandTest.php`**
- `:14 test_it_creates_an_admin`: command membuat admin.
- `:23 test_it_promotes_an_existing_account`: akun existing dipromosikan.
- `:34 test_it_rejects_invalid_input`: input invalid ditolak.

**`tests/Feature/FineServiceTest.php`**
- `:22 test_assessing_a_late_loan_records_the_fine_and_the_suspension`: denda dan skorsing.
- `:41 test_assessing_an_on_time_loan_leaves_no_fine_and_no_suspension`: tepat waktu tanpa denda.
- `:60 test_a_settled_fine_cannot_be_settled_twice`: denda lunas tidak bisa diulang.
- `:78 test_waiving_a_fine_clears_the_suspension`: waive mencabut skorsing.
- `:99 test_waiving_one_fine_keeps_suspension_from_another_unpaid_fine`: skorsing dari denda lain tetap.

**`tests/Feature/FineTest.php`**
- `:31 test_returning_on_time_creates_no_fine_and_no_suspension`: tepat waktu.
- `:47 test_returning_late_records_the_fine_and_starts_the_suspension`: denda dan skorsing.
- `:64 test_the_flash_message_shows_the_fine_and_suspension`: isi flash.
- `:76 test_returning_twice_does_not_charge_twice`: return ganda.
- `:89 test_the_rate_is_taken_from_the_loan_snapshot_not_the_current_config`: tarif snapshot.
- `:100 test_the_fine_is_capped`: batas denda dan skorsing.
- `:116 test_suspended_members_cannot_borrow`: skorsing memblokir.
- `:128 test_members_with_unpaid_fines_cannot_borrow`: denda memblokir.
- `:146 test_an_overdue_open_loan_blocks_new_borrows_and_is_not_auto_closed`: pinjaman telat tetap terbuka.
- `:161 test_a_member_can_borrow_again_after_returning_and_paying`: pinjam lagi setelah lunas dan skorsing lewat.
- `:183 test_admin_ending_an_overdue_loan_records_the_fine`: end telat mencatat denda.
- `:196 test_paying_twice_is_rejected`: bayar dua kali ditolak.
- `:205 test_waiving_requires_a_reason_and_keeps_the_fine_unpaid`: alasan wajib.
- `:216 test_waiving_clears_the_fine_and_the_suspension`: waive denda dan skorsing.
- `:235 test_my_loans_page_lists_the_fine`: halaman pinjaman menampilkan denda.
- `:248 test_my_loans_page_surfaces_overdue_loans_with_a_return_button`: tombol kembalikan untuk telat.
- `:258 test_an_overdue_loan_inside_the_grace_period_is_not_called_late`: masa tenggang.
- `:274 test_my_loans_page_calls_a_loan_late_once_the_grace_period_ends`: setelah tenggang disebut telat.
- `:284 test_book_page_explains_why_borrowing_is_blocked`: alasan blokir di halaman buku.
- `:295 test_admin_can_filter_unpaid_fines_only`: filter denda belum lunas.
- `:306 test_the_waive_dialog_names_a_generic_charge`: label "Bebaskan tagihan".
- `:313 test_only_admins_can_settle_or_waive_fines`: endpoint denda admin only.
- `:321 test_a_paid_fine_is_logged`: log `fine_paid`.
- `:331 test_borrowing_snapshots_the_current_rate`: snapshot tarif saat pinjam.
- `:342 test_ending_an_on_time_loan_records_no_fine_or_suspension`: end tepat waktu.
- `:357 test_a_settled_fine_cannot_be_waived`: denda lunas tidak bisa dibebaskan.
- `:370 test_a_waived_fine_cannot_be_settled_or_waived_again`: denda waived final.
- `:385 test_the_waive_reason_error_is_shown_on_the_admin_page`: error alasan tampil.
- `:395 test_a_zero_rate_still_suspends_but_charges_nothing`: tarif nol tetap menyusun skorsing.
- `:414 test_a_suspended_member_can_still_return_books`: anggota diskors masih bisa mengembalikan.
- `:425 test_an_existing_longer_suspension_is_not_shortened`: skorsing panjang tidak dipendekkan.

**`tests/Feature/HoldTest.php`**
- `:34 test_a_hold_can_be_placed_when_every_copy_is_unavailable`: pasang hold.
- `:49 test_a_hold_cannot_be_placed_while_a_copy_is_available`: hold ditolak bila masih ada copy.
- `:59 test_a_member_with_an_open_loan_cannot_hold_the_same_title`: pinjaman terbuka memblokir hold judul sama.
- `:73 test_a_member_cannot_exceed_the_hold_quota`: kuota 5 hold.
- `:87 test_holds_are_served_in_the_order_they_were_placed`: FIFO.
- `:100 test_checking_in_a_copy_reserves_it_for_the_first_hold`: check-in menahan copy.
- `:125 test_the_scheduled_command_expires_stale_holds`: command expiry.
- `:146 test_an_expired_hold_is_not_fulfilled_at_check_out`: hold kedaluwarsa tidak dipenuhi.
- `:168 test_only_the_holder_can_check_out_a_reserved_copy`: copy hanya untuk pemegang hold.
- `:196 test_a_suspended_member_is_skipped_when_a_copy_is_reserved`: anggota diskors dilewati.
- `:220 test_a_hold_blocks_staff_renewal`: hold memblokir perpanjangan petugas.
- `:236 test_an_uncollected_hold_expires_and_frees_the_copy`: hold tak diambil kedaluwarsa.
- `:257 test_an_active_hold_is_consumed_when_the_member_takes_a_new_copy`: hold terpakai saat ambil copy langsung.

**`tests/Feature/LoanAccessTest.php`**
- `:20 test_an_active_loan_is_found`: pinjaman aktif ditemukan.
- `:29 test_a_returned_loan_no_longer_counts_as_active`: returned bukan aktif.
- `:38 test_an_expired_loan_no_longer_counts_as_active`: expired bukan aktif.
- `:47 test_another_members_loan_is_not_mine`: pinjaman orang lain tidak ikut.
- `:57 test_a_returned_loan_still_counts_as_having_borrowed`: returned tetap "pernah meminjam".
- `:66 test_never_borrowed_is_false`: tanpa pinjaman false.

**`tests/Feature/LoanTest.php`**
- `:15 test_member_can_borrow_a_digital_book`: pinjam digital.
- `:29 test_double_borrowing_is_rejected`: pinjam ganda ditolak.
- `:40 test_books_without_pdf_cannot_be_borrowed`: tanpa PDF ditolak.
- `:49 test_active_loan_limit_is_enforced`: kuota 5.
- `:62 test_returning_frees_a_slot_and_allows_borrowing_again`: return membebaskan slot.
- `:75 test_an_overdue_loan_stays_open_and_blocks_borrowing`: telat tetap terbuka.
- `:87 test_overdue_loans_block_other_borrows_too`: telat memblokir buku lain.
- `:97 test_renewal_extends_the_due_date_once`: perpanjang sekali.
- `:111 test_returning_a_book_that_is_not_borrowed_reports_an_error`: return tanpa pinjaman error.
- `:118 test_the_database_prevents_two_open_loans_for_the_same_book`: unique index parsial.
- `:126 test_admins_cannot_use_member_features`: admin 403 di fitur anggota.
- `:136 test_guests_are_sent_to_login_when_borrowing`: guest dialihkan.
- `:143 test_loan_status_and_days_left_are_derived_from_dates`: status turunan.
- `:151 test_the_due_soon_window_comes_from_config`: ambang due-soon.
- `:162 test_an_overdue_loan_within_the_grace_period_has_no_late_days`: grace period.
- `:172 test_due_soon_scope_selects_loans_nearing_the_due_date`: scope dueSoon.
- `:180 test_my_loans_page_lists_active_loans_and_history`: rak pinjaman user sendiri.

**`tests/Feature/MembershipTest.php`**
- `:14 test_an_admin_can_update_a_member_card_and_expiry`: admin ubah kartu dan expiry.
- `:28 test_an_expired_membership_blocks_borrowing`: expiry memblokir.
- `:40 test_a_member_sees_their_number_on_the_profile`: nomor tampil di profil.
- `:48 test_the_home_page_no_longer_says_a_card_is_unneeded`: teks lama hilang.
- `:55 test_member_numbers_must_be_unique`: nomor duplikat ditolak.

**`tests/Feature/PhysicalCirculationTest.php`**
- `:27 test_physical_loans_use_their_own_loan_period`: masa pinjam fisik.
- `:37 test_a_new_member_has_a_number_and_can_borrow_a_digital_book`: nomor otomatis dan pinjam digital.
- `:53 test_a_copy_can_be_checked_out_by_a_staff_member`: check-out fisik.
- `:68 test_ending_a_physical_loan_checks_the_copy_in`: end fisik melakukan check-in.
- `:85 test_a_physical_loan_cannot_be_ended_without_a_condition`: kondisi wajib.
- `:99 test_an_inactive_member_cannot_take_a_copy`: akun nonaktif ditolak.
- `:114 test_a_physical_loan_does_not_grant_pdf_access`: fisik tidak membuka PDF.
- `:125 test_the_shelf_hides_self_return_for_a_physical_loan`: tombol kembalikan mandiri disembunyikan.

**`tests/Feature/ReaderTest.php`**
- `:14 test_reader_is_closed_without_an_active_loan`: tanpa pinjaman ditutup.
- `:23 test_active_loan_opens_the_reader_and_streams_the_pdf`: pinjaman aktif membuka PDF.
- `:36 test_pdf_supports_range_requests`: range request 206.
- `:47 test_access_closes_after_returning_the_book_or_after_expiry`: akses tertutup setelah return/expired.
- `:57 test_someone_elses_loan_grants_no_access`: pinjaman orang lain tidak berlaku.
- `:64 test_admin_can_read_any_pdf`: admin bisa baca semua.
- `:72 test_books_without_pdf_return_404`: tanpa PDF 404.
- `:80 test_missing_file_on_disk_returns_404`: berkas hilang 404.
- `:87 test_pdf_response_has_its_own_frame_policy`: CSP khusus PDF.

**`tests/Feature/ReviewAndFavoriteTest.php`**
- `:15 test_reviews_require_having_borrowed_the_book`: wajib pernah pinjam.
- `:24 test_one_review_per_member_per_book_and_it_can_be_edited`: satu ulasan per pasangan.
- `:38 test_review_html_is_escaped`: HTML di komentar di-escape.
- `:48 test_rating_must_be_between_one_and_five`: rating 1-5.
- `:59 test_review_form_is_hidden_until_the_member_has_borrowed`: form muncul setelah pinjam.
- `:70 test_owners_and_admins_can_delete_reviews_but_others_cannot`: penghapusan ulasan.
- `:87 test_favorites_can_be_added_and_removed_idempotently`: favorit idempoten.
- `:105 test_deleting_a_book_removes_its_reviews_favorites_and_loans`: cascade saat buku dihapus.

**`tests/Feature/SecurityHeadersTest.php`**
- `:13 test_pages_carry_security_headers`: header keamanan hadir.
- `:26 test_csp_does_not_allow_external_hosts_for_fonts_or_styles`: CSP tanpa host eksternal.
- `:35 test_pages_do_not_load_anything_from_third_parties`: tidak ada aset pihak ketiga.
- `:43 test_pages_contain_no_inline_scripts_or_styles_that_the_csp_would_block`: tidak ada inline script/style.
- `:55 test_error_pages_render_inside_the_site_layout`: halaman error memakai layout.
- `:60 test_health_endpoint_is_available`: `/up` 200.

**`tests/Feature/SeederTest.php`**
- `:14 test_seeded_accounts_have_member_numbers`: akun seeder punya nomor anggota.

**`tests/Unit/CatalogFiltersTest.php`**
- `:17 test_defaults_to_newest_without_a_search_term`: default Newest.
- `:26 test_defaults_to_relevance_when_searching`: default Relevance saat ada q.
- `:35 test_relevance_without_a_search_term_falls_back_to_newest`: fallback Relevance.
- `:40 test_years_outside_the_column_range_are_ignored`: tahun di luar rentang diabaikan.
- `:49 test_malformed_values_are_ignored_instead_of_failing`: input aneh aman.
- `:59 test_long_search_terms_are_truncated`: kata kunci dipotong 100 karakter.
- `:64 test_to_query_uses_url_parameter_names`: nama parameter URL.

**`tests/Unit/FinePolicyTest.php`**
- `:24 test_the_configured_rate_is_the_snapshot_for_new_loans`: tarif 1000.
- `:29 test_the_amount_uses_the_snapshot_rate`: `amount(3,500)=1500`.
- `:34 test_the_amount_is_capped`: cap 50000.
- `:39 test_zero_or_negative_values_never_produce_a_fine`: nilai nonpositif.
- `:46 test_suspension_days_follow_the_late_days`: 0/1/3 hari ke 0/2/6 hari.
- `:53 test_suspension_days_are_capped`: cap 30 hari.

**`tests/Unit/FineStatusTest.php`**
- `:11 test_no_amount_means_no_fine`: None.
- `:16 test_an_unsettled_amount_is_unpaid`: Unpaid.
- `:21 test_a_paid_amount_is_paid`: Paid.
- `:26 test_a_waived_amount_is_waived_even_if_it_was_paid_before`: Waived menang.
- `:33 test_only_an_unpaid_fine_can_be_settled_or_waived`: hanya Unpaid.

**`tests/Unit/LoanClockTest.php`**
- `:25 test_the_due_moment_itself_is_not_overdue`: tenggat bukan telat.
- `:31 test_returning_before_the_due_date_is_free`: sebelum tenggat bebas.
- `:36 test_returning_within_the_grace_period_is_free`: dalam grace bebas.
- `:41 test_the_grace_boundary_itself_is_still_free`: batas grace masih bebas.
- `:46 test_the_grace_end_is_the_moment_fines_start`: awal denda.
- `:51 test_one_second_past_the_grace_period_counts_as_one_day`: lewat grace 1 detik jadi 1 hari.
- `:56 test_partial_days_after_grace_round_up`: pembulatan ke atas.
- `:61 test_seventy_seven_hours_late_is_three_days_after_grace`: 77 jam jadi 3 hari.
- `:66 test_days_left_rounds_up_and_is_never_positive_after_the_due_date`: days_left.
- `:74 test_a_loan_is_due_soon_from_the_configured_threshold`: ambang 3 hari.
- `:81 test_the_due_soon_cutoff_is_the_threshold_in_the_future`: cutoff.

**`tests/Unit/LoanClosureTest.php`**
- `:17 test_an_on_time_return_says_just_that`: pesan tepat waktu.
- `:22 test_a_late_return_names_the_fine_and_the_late_days`: pesan denda.
- `:30 test_a_late_return_with_a_suspension_names_the_end_date`: tanggal akhir skorsing.
- `:40 test_a_zero_fine_still_reports_the_suspension`: denda nol tetap lapor skorsing.
- `:51 test_a_past_suspension_is_not_announced`: skorsing lampau tidak diumumkan.
- `:61 test_the_admin_message_reports_the_recorded_fine`: pesan admin.

**`tests/Unit/SearchTermTest.php`**
- `:10 test_the_pattern_matches_anywhere`: pola `%budi%`.
- `:15 test_the_prefix_pattern_matches_the_start`: pola `budi%`.
- `:20 test_wildcards_are_escaped`: wildcard di-escape.
- `:27 test_an_empty_term_is_blank`: string kosong blank.

---

## 10. DAFTAR HALAMAN/UI

### Publik dan anggota

| Halaman | Route | Fungsi | View |
|---|---|---|---|
| Beranda | `home` (`GET /`) | Statistik, pencarian, buku terbaru, kategori, cara meminjam | `resources/views/home.blade.php` |
| Katalog | `catalog.index` (`GET /katalog`) | Pencarian, filter, urutan, paginasi | `resources/views/catalog/index.blade.php` |
| Kategori | `categories.index` (`GET /kategori`) | Daftar kategori | `resources/views/categories/index.blade.php` |
| Detail buku | `books.show` (`GET /buku/{book}`) | Detail dan semua aksi anggota | `resources/views/books/show.blade.php` |
| Pembaca PDF | `reader.show` (`GET /baca/{book}`) | Tampilan iframe PDF | `resources/views/books/reader.blade.php` |
| Masuk | `login` (`GET /masuk`) | Form login | `resources/views/auth/login.blade.php` |
| Daftar | `register` (`GET /daftar`) | Form registrasi | `resources/views/auth/register.blade.php` |
| Rak pinjaman | `account.loans` (`GET /pinjaman`) | Aktif, telat, hold, riwayat, denda | `resources/views/account/loans.blade.php` |
| Rak favorit | `account.favorites` (`GET /favorit`) | Daftar favorit | `resources/views/account/favorites.blade.php` |
| Profil | `account.profile` (`GET /profil`) | Data akun, nomor anggota, ganti sandi | `resources/views/account/profile.blade.php` |

### Admin

| Halaman | Route | Fungsi | View |
|---|---|---|---|
| Dasbor | `admin.dashboard` (`GET /admin`) | Statistik, grafik 14 hari, buku terlaris, aktivitas | `resources/views/admin/dashboard.blade.php` |
| Buku | `admin.books.index` | Daftar, cari, filter, aksi | `resources/views/admin/books/index.blade.php` |
| Form buku | `admin.books.create` / `admin.books.edit` | Tambah/ubah buku dan unggah berkas | `resources/views/admin/books/form.blade.php` |
| Eksemplar | `admin.copies.index` | Daftar dan cari eksemplar | `resources/views/admin/copies/index.blade.php` |
| Form eksemplar | `admin.copies.create` / `admin.copies.edit` | Tambah/ubah eksemplar | `resources/views/admin/copies/form.blade.php` |
| Kategori | `admin.categories.index` | Tambah, ubah, hapus kategori | `resources/views/admin/categories.blade.php` |
| Sirkulasi | `admin.circulation.index` | Check-out, check-in, hold siap diambil | `resources/views/admin/circulation.blade.php` |
| Peminjaman | `admin.loans.index` | Daftar, perpanjang, check-in, denda, biaya ganti | `resources/views/admin/loans.blade.php` |
| Ulasan | `admin.reviews.index` | Moderasi ulasan | `resources/views/admin/reviews.blade.php` |
| Pengguna | `admin.users.index` | Status, peran, kartu, reset sandi | `resources/views/admin/users.blade.php` |
| Kartu anggota | `admin.users.membership.edit` | Ubah nomor dan expiry | `resources/views/admin/membership.blade.php` |
| Log aktivitas | `admin.logs.index` | Daftar dan pencarian log | `resources/views/admin/logs.blade.php` |

Halaman turunan resource `admin.copies.show`: rute terdaftar otomatis, tetapi controller `show` dan view `admin/copies/show`: **TIDAK DITEMUKAN**.

Halaman error: `403`, `404`, `405`, `419`, `429`, `500`, `503` di `resources/views/errors/`. Halaman kartu anggota cetak/download sisi anggota: **TIDAK DITEMUKAN**.

---

## 11. KETERBATASAN

### Fitur yang tidak ada

- Notifikasi email, lupa sandi via email, dan penggunaan `Mail::`/`Notification::`/`Notifiable`: **TIDAK DITEMUKAN**. Mailer default `log` (`config/mail.php:17`). Reset sandi dilakukan admin (`README.md:202`).
- Hold untuk buku digital: **TIDAK DITEMUKAN**. Hold hanya untuk eksemplar fisik (`app/Services/HoldService.php:38-44`).
- Akses PDF di luar pinjaman digital aktif: **TIDAK DITEMUKAN**, memang diblokir sengaja (`app/Policies/BookPolicy.php:14-21`). Admin adalah pengecualian eksplisit.
- Rute API: **TIDAK DITEMUKAN**.
- CI workflow (`.github/workflows`): **TIDAK DITEMUKAN**.
- Tambah pengguna oleh admin lewat UI: **TIDAK DITEMUKAN**. Hanya registrasi publik dan command `library:create-admin`.
- Alur persetujuan ulasan: **TIDAK DITEMUKAN**. Hanya hapus oleh pemilik/admin.
- Kuota terpisah digital dan fisik: **TIDAK DITEMUKAN**. Kuota selalu gabungan.
- Pembayaran daring, ekspor laporan PDF/Excel, multi-cabang, reservasi tanggal tertentu, verifikasi email: **TIDAK DITEMUKAN** (`PROJECT_CONTEXT.md:13,44,61,269`).
- Metadata log terstruktur/JSON: **TIDAK DITEMUKAN**. Detail disimpan di kolom `message`.
- Pendaftaran policy manual: **TIDAK DITEMUKAN** (auto-discovery).
- Konfigurasi CSRF kustom: **TIDAK DITEMUKAN**.
- Controller `show` dan view untuk `admin.copies.show`: **TIDAK DITEMUKAN**.
- Halaman cetak/download kartu anggota: **TIDAK DITEMUKAN**.
- Relasi inverse `HasMany` dari `User`, `Book`, `BookCopy`, `Loan` ke `Hold`: **TIDAK DITEMUKAN**.

### Fitur yang masih setengah jadi atau perlu perhatian

- Pengecekan keberadaan berkas PDF sebelum pinjam digital belum ada. `Book::has_pdf` hanya memeriksa `pdf_path !== null` (`app/Models/Book.php:77-80`); pembaca baru mengecek file saat mengakses (`app/Http/Controllers/ReaderController.php:45`).
- Audit check-in belum menyimpan kondisi eksemplar. `Audit::copyCheckedIn` hanya menulis kode eksemplar, user, dan buku (`app/Services/Audit.php:136-140`).
- Total tunggakan menggabungkan denda dan biaya ganti, tetapi pesan blokir memakai kata "denda belum lunas" (`app/Services/FineService.php:44-50`, `app/Support/BorrowBlock.php:76`).
- Ulasan boleh ditulis oleh anggota yang masih punya pinjaman aktif atau telat, karena syaratnya "pernah meminjam" (`app/Services/LoanAccess.php:29-35`).
- Alasan pembebasan berupa teks bebas 5 sampai 255 karakter, tanpa kode alasan atau tingkat persetujuan (`app/Http/Controllers/Admin/LoanFineController.php:32-37,61-66`).
- Riwayat pinjaman anggota dibatasi 100 baris tanpa paginasi (`app/Http/Controllers/Account/LoanController.php:27`).
- Tidak ada tampilan ringkasan sikap anggota sebelum petugas melakukan check-out (`app/Http/Controllers/Admin/CirculationController.php:38-70`).
- Petugas tidak bisa membatalkan hold lewat rute karena `holds.destroy` berada di grup `can:member`, meskipun service mengizinkan admin (`routes/web.php:57`, `app/Services/HoldService.php:69-71`).
- Tanpa ekstensi GD, sampul tetap disimpan apa adanya meski sudah lolos validasi `image` (`app/Services/BookMedia.php:36-41`).
- `Model::shouldBeStrict()` hanya aktif di luar produksi (`app/Providers/AppServiceProvider.php:24-28`).
- Nilai numerik di `config/library.php` tidak divalidasi, sehingga nilai seperti `LOAN_DAYS=0` dapat diterima.
- Akun demo seeder harus diganti atau dihapus sebelum produksi (`README.md:38-39,200-201`).
- `public/build` tidak disimpan di git, sehingga aset harus dibangun di server setiap deploy (`README.md:192`).

### Migrasi/data

- M10 melakukan backfill yang menutup pinjaman telat tanpa denda dan mengisi `fine_rate` untuk pinjaman masih berjalan (`database/migrations/2026_11_18_000001_add_fines_to_loans_and_suspend_to_users.php:35-48`).
- M12 mengisi nomor anggota untuk akun lama (`database/migrations/2026_11_20_000002_add_hybrid_circulation_fields.php:30-35`).
- M14 mengubah nilai `reserved` menjadi `on_hold` dan mengganti CHECK constraint (`database/migrations/2026_11_22_000002_rename_reserved_copy_status.php:10-15`).
- Tidak ada tes khusus untuk backfill migrasi: **TIDAK DITEMUKAN**.

### Scheduler

Scheduler sudah ada, bukan keterbatasan lagi. Command `library:expire-holds` dijalankan tiap jam (`bootstrap/app.php:32-34`, `app/Console/Commands/ExpireStaleHolds.php:8-20`) dan dokumentasi cron ada di `README.md:194-200`.
