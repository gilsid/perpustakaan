<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Category;
use App\Models\Hold;
use App\Models\Loan;
use App\Models\Review;
use App\Models\User;
use App\Support\Rupiah;

/**
 * Titik masuk tunggal untuk jejak audit. Nama metodenya memakai kejadian
 * domain, bukan kode aksi mentah, jadi pemanggil tidak perlu tahu nilai enum
 * atau susunan kalimatnya. Aktor selalu eksplisit; hanya IP yang dibaca dari
 * request karena itu satu-satunya hal yang memang milik HTTP.
 */
final class Audit
{
    public function registered(User $user, User $actor): void
    {
        $this->record(ActivityAction::Register, 'user', $user->id, "Anggota baru mendaftar: {$user->email}", $actor);
    }

    public function bookCreated(Book $book, User $actor): void
    {
        $this->record(ActivityAction::CreateBook, 'book', $book->id, "Menambah buku \"{$book->title}\"", $actor);
    }

    public function bookUpdated(Book $book, User $actor): void
    {
        $this->record(ActivityAction::UpdateBook, 'book', $book->id, "Mengubah buku \"{$book->title}\"", $actor);
    }

    public function bookDeleted(Book $book, User $actor): void
    {
        $this->record(ActivityAction::DeleteBook, 'book', $book->id, "Menghapus buku \"{$book->title}\"", $actor);
    }

    public function categoryCreated(Category $category, User $actor): void
    {
        $this->record(ActivityAction::CreateCategory, 'category', $category->id, "Menambah kategori \"{$category->name}\"", $actor);
    }

    public function categoryUpdated(Category $category, string $oldName, User $actor): void
    {
        $this->record(ActivityAction::UpdateCategory, 'category', $category->id, "Mengubah kategori \"{$oldName}\" menjadi \"{$category->name}\"", $actor);
    }

    public function categoryDeleted(Category $category, User $actor): void
    {
        $this->record(ActivityAction::DeleteCategory, 'category', $category->id, "Menghapus kategori \"{$category->name}\"", $actor);
    }

    public function userRoleChanged(User $user, UserRole $role, User $actor): void
    {
        $this->record(ActivityAction::ChangeRole, 'user', $user->id, "Mengubah peran {$user->email} menjadi {$role->value}", $actor);
    }

    public function userPasswordReset(User $user, User $actor): void
    {
        $this->record(ActivityAction::ResetPassword, 'user', $user->id, "Mereset kata sandi {$user->email}", $actor);
    }

    public function userActivated(User $user, User $actor): void
    {
        $this->record(ActivityAction::ActivateUser, 'user', $user->id, "Mengaktifkan akun {$user->email}", $actor);
    }

    public function userDeactivated(User $user, User $actor): void
    {
        $this->record(ActivityAction::DeactivateUser, 'user', $user->id, "Menonaktifkan akun {$user->email}", $actor);
    }

    public function reviewDeleted(Review $review, User $actor): void
    {
        $this->record(ActivityAction::DeleteReview, 'review', $review->id, "Menghapus ulasan #{$review->id} pada buku #{$review->book_id}", $actor);
    }

    public function holdPlaced(Hold $hold, User $actor): void
    {
        $this->record(ActivityAction::HoldPlaced, 'hold', $hold->id, "Memasang hold untuk buku #{$hold->book_id} (anggota #{$hold->user_id})", $actor);
    }

    public function holdFulfilled(Hold $hold, User $actor): void
    {
        $this->record(ActivityAction::HoldFulfilled, 'hold', $hold->id, "Hold #{$hold->id} dipenuhi oleh pinjaman #{$hold->loan_id}", $actor);
    }

    public function holdCancelled(Hold $hold, User $actor): void
    {
        $this->record(ActivityAction::HoldCancelled, 'hold', $hold->id, "Membatalkan hold #{$hold->id} untuk buku #{$hold->book_id}", $actor);
    }

    public function holdExpired(Hold $hold): void
    {
        $this->record(ActivityAction::HoldExpired, 'hold', $hold->id, "Hold #{$hold->id} untuk buku #{$hold->book_id} kedaluwarsa", null);
    }

    public function loanBorrowed(Loan $loan, User $actor): void
    {
        $this->record(ActivityAction::BorrowLoan, 'loan', $loan->id, "Meminjam buku #{$loan->book_id} (pinjaman #{$loan->id}, anggota #{$loan->user_id})", $actor);
    }

    public function copyCheckedOut(Loan $loan, User $actor): void
    {
        $code = $loan->copy()->value('code') ?? '#'.$loan->copy_id;
        $this->record(ActivityAction::CheckoutCopy, 'loan', $loan->id, "Check-out eksemplar {$code} untuk anggota #{$loan->user_id} (buku #{$loan->book_id})", $actor);
    }

    public function copyCheckedIn(Loan $loan, User $actor): void
    {
        $code = $loan->copy()->value('code') ?? '#'.$loan->copy_id;
        $this->record(ActivityAction::CheckinCopy, 'loan', $loan->id, "Check-in eksemplar {$code} dari anggota #{$loan->user_id} (buku #{$loan->book_id})", $actor);
    }

    public function loanRenewed(Loan $loan, User $actor): void
    {
        $this->record(ActivityAction::RenewLoan, 'loan', $loan->id, "Memperpanjang pinjaman #{$loan->id} (buku #{$loan->book_id}) sampai {$loan->due_at->translatedFormat('j M Y')}", $actor);
    }

    public function loanReturned(Loan $loan, User $actor): void
    {
        $this->record(ActivityAction::ReturnLoan, 'loan', $loan->id, "Mengembalikan pinjaman #{$loan->id} (buku #{$loan->book_id}, anggota #{$loan->user_id})", $actor);
    }

    public function loanEnded(Loan $loan, User $actor): void
    {
        $this->record(ActivityAction::FinishLoan, 'loan', $loan->id, "Mengakhiri pinjaman #{$loan->id} (buku #{$loan->book_id}, anggota #{$loan->user_id})", $actor);
    }

    public function fineAssessed(Loan $loan, User $actor): void
    {
        $message = $loan->fine_amount > 0
            ? "Denda {$loan->late_days} hari telat = ".Rupiah::format($loan->fine_amount)." untuk pinjaman #{$loan->id} (anggota #{$loan->user_id})"
            : "Keterlambatan {$loan->late_days} hari tanpa denda (tarif 0) untuk pinjaman #{$loan->id} (anggota #{$loan->user_id})";

        $this->record(ActivityAction::FineAssessed, 'loan', $loan->id, $message, $actor);
    }

    public function finePaid(Loan $loan, User $actor): void
    {
        $this->record(ActivityAction::FinePaid, 'loan', $loan->id, 'Menandai lunas denda '.Rupiah::format($loan->fine_amount)." pinjaman #{$loan->id} (anggota #{$loan->user_id})", $actor);
    }

    public function fineWaived(Loan $loan, User $actor, string $reason): void
    {
        $this->record(ActivityAction::FineWaived, 'loan', $loan->id, 'Membebaskan denda '.Rupiah::format($loan->fine_amount)." pinjaman #{$loan->id} (anggota #{$loan->user_id}): {$reason}", $actor);
    }

    private function record(ActivityAction $action, string $entityType, ?int $entityId, string $message, ?User $actor): void
    {
        ActivityLog::create([
            'user_id' => $actor?->id,
            'action' => $action->value,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => $message,
            'ip' => request()->ip(),
        ]);
    }
}
