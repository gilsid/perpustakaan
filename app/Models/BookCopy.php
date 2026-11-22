<?php

namespace App\Models;

use App\Enums\BookCopyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu unit fisik dari sebuah judul buku, dikenali oleh barcode/kodenya. */
#[Fillable(['book_id', 'code', 'location', 'status', 'replacement_cost'])]
class BookCopy extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BookCopyStatus::class,
            'replacement_cost' => 'integer',
        ];
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** @return HasMany<Loan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'copy_id');
    }

    /** @param Builder<BookCopy> $query */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', BookCopyStatus::Available);
    }

    public function isLoanable(): bool
    {
        return $this->status->isLoanable();
    }
}
