<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aturan peminjaman
    |--------------------------------------------------------------------------
    */
    'loan' => [
        'days' => (int) env('LOAN_DAYS', 14),
        'max_active' => (int) env('LOAN_MAX_ACTIVE', 5),
        'max_renewals' => (int) env('LOAN_MAX_RENEWALS', 1),
        'due_soon_days' => (int) env('LOAN_DUE_SOON_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Denda keterlambatan
    |--------------------------------------------------------------------------
    | Tarif di-snapshot ke setiap pinjaman saat meminjam, sehingga mengubah
    | nilai di sini tidak mengubah tagihan yang sudah berjalan.
    */
    'fine' => [
        'rate_per_day' => (int) env('FINE_RATE_PER_DAY', 1000),
        'max' => (int) env('FINE_MAX', 50000),
        'grace_hours' => (int) env('FINE_GRACE_HOURS', 24),
        'suspend_factor' => (int) env('FINE_SUSPEND_FACTOR', 2),
        'suspend_max_days' => (int) env('FINE_SUSPEND_MAX_DAYS', 30),
    ],

    'hold' => [
        'max_active' => (int) env('HOLD_MAX_ACTIVE', 5),
        'pickup_days' => (int) env('HOLD_PICKUP_DAYS', 7),
    ],

    // Ulasan hanya boleh ditulis anggota yang pernah meminjam bukunya.
    'review_requires_loan' => (bool) env('LIBRARY_REVIEW_REQUIRES_LOAN', true),

    'per_page' => 12,
    'admin_per_page' => 20,

    /*
    |--------------------------------------------------------------------------
    | Unggahan (dalam kilobyte, sesuai aturan validasi Laravel)
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'cover_max_kb' => 3 * 1024,
        'pdf_max_kb' => 50 * 1024,
        'cover_max_width' => 800,
        'cover_max_height' => 1200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pembatasan percobaan masuk
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'min_password' => 8,
        'max_attempts' => 5,        // gagal per email + IP
        'max_attempts_per_ip' => 20,
        'lockout_minutes' => 15,
    ],
];
