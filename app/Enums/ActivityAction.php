<?php

namespace App\Enums;

/**
 * Jenis aktivitas yang dicatat. Nilai string lama dipertahankan agar baris log
 * yang sudah ada tetap terbaca; label hanya untuk tampilan.
 */
enum ActivityAction: string
{
    case Register = 'register';
    case CreateBook = 'create_book';
    case UpdateBook = 'update_book';
    case DeleteBook = 'delete_book';
    case CreateCategory = 'create_category';
    case UpdateCategory = 'update_category';
    case DeleteCategory = 'delete_category';
    case ChangeRole = 'change_role';
    case ResetPassword = 'reset_password';
    case ActivateUser = 'activate_user';
    case DeactivateUser = 'deactivate_user';
    case BorrowLoan = 'borrow_loan';
    case CheckoutCopy = 'checkout_copy';
    case CheckinCopy = 'checkin_copy';
    case RenewLoan = 'renew_loan';
    case ReturnLoan = 'return_loan';
    case FinishLoan = 'finish_loan';
    case DeleteReview = 'delete_review';
    case HoldPlaced = 'hold_placed';
    case HoldFulfilled = 'hold_fulfilled';
    case HoldCancelled = 'hold_cancelled';
    case HoldExpired = 'hold_expired';
    case FineAssessed = 'fine_assessed';
    case FinePaid = 'fine_paid';
    case FineWaived = 'fine_waived';

    public function label(): string
    {
        return match ($this) {
            self::Register => 'Pendaftaran anggota',
            self::CreateBook => 'Menambah buku',
            self::UpdateBook => 'Mengubah buku',
            self::DeleteBook => 'Menghapus buku',
            self::CreateCategory => 'Menambah kategori',
            self::UpdateCategory => 'Mengubah kategori',
            self::DeleteCategory => 'Menghapus kategori',
            self::ChangeRole => 'Mengubah peran',
            self::ResetPassword => 'Reset kata sandi',
            self::ActivateUser => 'Mengaktifkan akun',
            self::DeactivateUser => 'Menonaktifkan akun',
            self::BorrowLoan => 'Meminjam buku',
            self::CheckoutCopy => 'Check-out eksemplar fisik',
            self::CheckinCopy => 'Check-in eksemplar fisik',
            self::RenewLoan => 'Memperpanjang pinjaman',
            self::ReturnLoan => 'Mengembalikan buku',
            self::FinishLoan => 'Mengakhiri pinjaman',
            self::DeleteReview => 'Menghapus ulasan',
            self::HoldPlaced => 'Hold dipasang',
            self::HoldFulfilled => 'Hold dipenuhi',
            self::HoldCancelled => 'Hold dibatalkan',
            self::HoldExpired => 'Hold kedaluwarsa',
            self::FineAssessed => 'Denda dicatat',
            self::FinePaid => 'Denda dilunasi',
            self::FineWaived => 'Denda dibebaskan',
        };
    }
}
