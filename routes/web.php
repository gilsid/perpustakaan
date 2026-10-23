<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ReaderController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// ----- publik -----
Route::get('/', HomeController::class)->name('home');
Route::get('/katalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/kategori', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/buku/{book}', [BookController::class, 'show'])->name('books.show');

// ----- tamu -----
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
});

Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ----- profil (boleh diakses meski kata sandi masih sementara) -----
Route::middleware('auth')->prefix('profil')->name('account.')->group(function () {
    Route::get('/', [Account\ProfileController::class, 'edit'])->name('profile');
    Route::put('/', [Account\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/sandi', [Account\PasswordController::class, 'update'])->name('password.update');
});

// ----- pengguna masuk -----
Route::middleware(['auth', 'password.fresh'])->group(function () {
    Route::get('/baca/{book}', [ReaderController::class, 'show'])->name('reader.show');
    Route::get('/berkas/{book}', [ReaderController::class, 'file'])->name('reader.file');
    Route::delete('/ulasan/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // ----- anggota -----
    Route::middleware('can:member')->group(function () {
        Route::get('/pinjaman', [Account\LoanController::class, 'index'])->name('account.loans');
        Route::get('/favorit', [Account\FavoriteController::class, 'index'])->name('account.favorites');

        Route::post('/buku/{book}/pinjam', [LoanController::class, 'store'])->name('loans.store');
        Route::patch('/buku/{book}/pinjam', [LoanController::class, 'update'])->name('loans.update');
        Route::delete('/buku/{book}/pinjam', [LoanController::class, 'destroy'])->name('loans.destroy');

        Route::put('/buku/{book}/favorit', [FavoriteController::class, 'store'])->name('favorites.store');
        Route::delete('/buku/{book}/favorit', [FavoriteController::class, 'destroy'])->name('favorites.destroy');

        Route::put('/buku/{book}/ulasan', [ReviewController::class, 'store'])->name('reviews.store');
    });

    // ----- admin -----
    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::resource('buku', Admin\BookController::class)->except('show')->parameters(['buku' => 'book'])->names('books');
        Route::resource('kategori', Admin\CategoryController::class)->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['kategori' => 'category'])->names('categories');

        Route::get('/peminjaman', [Admin\LoanController::class, 'index'])->name('loans.index');
        Route::patch('/peminjaman/{loan}', [Admin\LoanController::class, 'update'])->name('loans.update');

        Route::get('/ulasan', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::delete('/ulasan/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::get('/pengguna', [Admin\UserController::class, 'index'])->name('users.index');
        Route::patch('/pengguna/{user}/status', [Admin\UserStatusController::class, 'update'])->name('users.status');
        Route::patch('/pengguna/{user}/peran', [Admin\UserRoleController::class, 'update'])->name('users.role');
        Route::post('/pengguna/{user}/reset-sandi', [Admin\UserPasswordResetController::class, 'store'])->name('users.reset-password');

        Route::get('/log', [Admin\ActivityLogController::class, 'index'])->name('logs.index');
    });
});
