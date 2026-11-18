<?php

namespace App\Models;

use App\Enums\FineStatus;
use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'book_id', 'borrowed_at', 'due_at', 'returned_at', 'renewals',
    'fine_rate', 'late_days', 'fine_amount', 'fine_paid_at', 'fine_waived_at', 'fine_waived_by'])]
class Loan extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'renewals' => 'integer',
            'fine_rate' => 'integer',
            'late_days' => 'integer',
            'fine_amount' => 'integer',
            'fine_paid_at' => 'datetime',
            'fine_waived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** @return BelongsTo<User, $this> */
    public function fineWaivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fine_waived_by');
    }

    // ------------------------------------------------------------------- scopes

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('returned_at')->where('due_at', '>', now());
    }

    public function scopeExpired(Builder $query): void
    {
        $query->whereNull('returned_at')->where('due_at', '<=', now());
    }

    public function scopeReturned(Builder $query): void
    {
        $query->whereNotNull('returned_at');
    }

    /** Pinjaman yang dendanya masih menjadi tagihan terbuka. */
    public function scopeUnpaidFine(Builder $query): void
    {
        $query->where('fine_amount', '>', 0)
            ->whereNull('fine_paid_at')
            ->whereNull('fine_waived_at');
    }

    /** Pinjaman telat yang belum dikembalikan (dasar pemblokiran peminjaman baru). */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('returned_at')->where('due_at', '<=', now());
    }

    /** Riwayat = sudah dikembalikan atau sudah berakhir. */
    public function scopeFinished(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNotNull('returned_at')->orWhere('due_at', '<=', now()));
    }

    public function scopeWithStatus(Builder $query, ?LoanStatus $status): void
    {
        match ($status) {
            LoanStatus::Active => $query->active(),
            LoanStatus::Expired => $query->expired(),
            LoanStatus::Returned => $query->returned(),
            default => null,
        };
    }

    // ---------------------------------------------------------------- accessors

    protected function status(): Attribute
    {
        return Attribute::get(fn (): LoanStatus => match (true) {
            $this->returned_at !== null => LoanStatus::Returned,
            $this->due_at->isFuture() => LoanStatus::Active,
            default => LoanStatus::Expired,
        });
    }

    /** Sisa hari (dibulatkan ke atas); ≤ 0 bila tenggat sudah lewat. */
    protected function daysLeft(): Attribute
    {
        return Attribute::get(fn (): int => (int) ceil(($this->due_at->getTimestamp() - time()) / 86400));
    }

    protected function daysLeftLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->days_left <= 0 ? 'hari ini' : $this->days_left.' hari lagi');
    }

    /** Persentase masa pinjam yang sudah terpakai (0–100). */
    protected function elapsedPercent(): Attribute
    {
        return Attribute::get(function (): int {
            $total = max(1, $this->due_at->getTimestamp() - $this->borrowed_at->getTimestamp());

            return (int) min(100, max(0, round((time() - $this->borrowed_at->getTimestamp()) / $total * 100)));
        });
    }

    protected function canRenew(): Attribute
    {
        return Attribute::get(fn (): bool => $this->renewals < config('library.loan.max_renewals'));
    }

    protected function fineStatus(): Attribute
    {
        return Attribute::get(fn (): FineStatus => match (true) {
            $this->fine_amount <= 0 => FineStatus::None,
            $this->fine_waived_at !== null => FineStatus::Waived,
            $this->fine_paid_at !== null => FineStatus::Paid,
            default => FineStatus::Unpaid,
        });
    }

    /** Hari telat: hasil snapshot untuk pinjaman tertutup, hitungan berjalan untuk yang masih terbuka. */
    protected function daysLate(): Attribute
    {
        return Attribute::get(fn (): int => match (true) {
            $this->returned_at !== null => $this->late_days,
            $this->due_at->isPast() => (int) ceil((time() - $this->due_at->getTimestamp()) / 86400),
            default => 0,
        });
    }
}
