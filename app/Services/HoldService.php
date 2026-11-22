<?php

namespace App\Services;

use App\Enums\BookCopyStatus;
use App\Enums\HoldStatus;
use App\Exceptions\LoanException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Hold;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class HoldService
{
    public function __construct(
        private readonly BorrowEligibility $eligibility,
        private readonly LoanAccess $access,
        private readonly Audit $audit,
    ) {}

    /** @throws LoanException */
    public function place(User $user, Book $book): Hold
    {
        if ($block = $this->eligibility->blocksForMember($user)[0] ?? null) {
            throw new LoanException($block->message());
        }

        if ($this->access->hasOpenLoan($user, $book)) {
            throw new LoanException('Anda sudah meminjam buku ini.');
        }

        if (! BookCopy::query()->where('book_id', $book->id)->exists()) {
            throw new LoanException('Buku ini tidak punya eksemplar fisik.');
        }

        if (BookCopy::query()->where('book_id', $book->id)->where('status', BookCopyStatus::Available)->exists()) {
            throw new LoanException('Masih ada eksemplar tersedia. Ambil langsung di perpustakaan.');
        }

        if ($this->activeCount($user) >= (int) config('library.hold.max_active')) {
            throw new LoanException('Batas hold tercapai. Batalkan salah satu hold dulu.');
        }

        $hold = Hold::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => HoldStatus::Active,
        ]);

        $this->audit->holdPlaced($hold, $user);

        return $hold;
    }

    /** @throws LoanException */
    public function cancel(Hold $hold, User $actor): void
    {
        if (! $hold->status->isPending()) {
            throw new LoanException('Hold ini sudah tidak aktif.');
        }

        if ($hold->user_id !== $actor->id && ! $actor->isAdmin()) {
            throw new LoanException('Anda tidak bisa membatalkan hold ini.');
        }

        $this->release($hold);
        $hold->update(['status' => HoldStatus::Cancelled]);
        $this->audit->holdCancelled($hold, $actor);

        $this->promote($hold->book_id);
    }

    public function firstForBook(Book $book): ?Hold
    {
        return Hold::query()
            ->where('book_id', $book->id)
            ->where('status', HoldStatus::Active)
            ->orderBy('id')
            ->first();
    }

    public function hasPendingFor(Book $book): bool
    {
        return Hold::query()->where('book_id', $book->id)->pending()->exists();
    }

    /** @return Collection<int, Hold> */
    public function forUser(User $user): Collection
    {
        $this->expireStale();

        return Hold::query()->with('book')
            ->where('user_id', $user->id)
            ->whereIn('status', [HoldStatus::Active, HoldStatus::Ready])
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, Hold> */
    public function readyHolds(): Collection
    {
        $this->expireStale();

        return Hold::query()->with(['book', 'user', 'copy'])
            ->where('status', HoldStatus::Ready)
            ->orderBy('id')
            ->get();
    }

    public function reserveForCheckIn(BookCopy $copy): ?Hold
    {
        $this->expireStale();

        $hold = $this->firstForBook(Book::findOrFail($copy->book_id));
        if ($hold === null) {
            return null;
        }

        $hold->update([
            'status' => HoldStatus::Ready,
            'copy_id' => $copy->id,
            'ready_at' => now(),
            'pickup_expires_at' => now()->addDays((int) config('library.hold.pickup_days')),
        ]);
        $copy->update(['status' => BookCopyStatus::OnHold]);

        return $hold;
    }

    public function fulfill(Hold $hold, Loan $loan, User $actor): void
    {
        $hold->update(['status' => HoldStatus::Fulfilled, 'fulfilled_at' => now(), 'loan_id' => $loan->id]);
        $this->audit->holdFulfilled($hold, $actor);
    }

    public function expireStale(): void
    {
        Hold::query()
            ->where('status', HoldStatus::Ready)
            ->where('pickup_expires_at', '<=', now())
            ->get()
            ->each(function (Hold $hold): void {
                $this->release($hold);
                $hold->update(['status' => HoldStatus::Expired]);
                $this->audit->holdExpired($hold);
                $this->promote($hold->book_id);
            });
    }

    private function activeCount(User $user): int
    {
        return Hold::query()->where('user_id', $user->id)->pending()->count();
    }

    private function release(Hold $hold): void
    {
        if ($hold->copy_id === null) {
            return;
        }

        BookCopy::query()->whereKey($hold->copy_id)->update(['status' => BookCopyStatus::Available]);
    }

    private function promote(int $bookId): void
    {
        $hold = Hold::query()->where('book_id', $bookId)->where('status', HoldStatus::Active)->orderBy('id')->first();
        if ($hold === null) {
            return;
        }

        $copy = BookCopy::query()->where('book_id', $bookId)->where('status', BookCopyStatus::Available)->orderBy('id')->first();
        if ($copy === null) {
            return;
        }

        $hold->update([
            'status' => HoldStatus::Ready,
            'copy_id' => $copy->id,
            'ready_at' => now(),
            'pickup_expires_at' => now()->addDays((int) config('library.hold.pickup_days')),
        ]);
        $copy->update(['status' => BookCopyStatus::OnHold]);
    }
}
