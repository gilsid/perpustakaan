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
