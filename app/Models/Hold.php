<?php

namespace App\Models;

use App\Enums\HoldStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'book_id', 'copy_id', 'loan_id', 'status', 'ready_at', 'pickup_expires_at', 'fulfilled_at'])]
class Hold extends Model
{
    protected function casts(): array
    {
        return [
            'status' => HoldStatus::class,
            'ready_at' => 'datetime',
            'pickup_expires_at' => 'datetime',
            'fulfilled_at' => 'datetime',
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

    /** @return BelongsTo<BookCopy, $this> */
    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    /** @param Builder<Hold> $query */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', [HoldStatus::Active, HoldStatus::Ready]);
    }
}
