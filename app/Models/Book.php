<?php

namespace App\Models;

use App\Enums\BookCopyStatus;
use App\Enums\CatalogSort;
use App\Support\CatalogFilters;
use App\Support\SearchTerm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'title', 'author', 'isbn', 'publisher', 'published_year', 'language', 'pages',
    'call_number', 'category_id', 'description', 'cover_path', 'pdf_path',
])]
class Book extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_year' => 'integer',
            'pages' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Loan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** @return HasMany<BookCopy, $this> */
    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    /** @return HasMany<BookCopy, $this> */
    public function availableCopies(): HasMany
    {
        return $this->copies()->where('status', BookCopyStatus::Available);
    }

    /** @return BelongsToMany<User, $this> */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    // ---------------------------------------------------------------- accessors

    /** Buku hanya bisa dipinjam digital bila punya berkas PDF. */
    protected function hasPdf(): Attribute
    {
        return Attribute::get(fn (): bool => $this->pdf_path !== null);
    }

    /** Jumlah eksemplar fisik yang siap dipinjam. */
    protected function availableCopiesCount(): Attribute
    {
        return Attribute::get(fn (): int => (int) ($this->attributes['available_copies_count']
            ?? $this->availableCopies()->count()));
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null);
    }

    /** Rating rata-rata (0 bila belum ada ulasan). Butuh scope withStats(). */
    protected function rating(): Attribute
    {
        return Attribute::get(fn (): float => round((float) ($this->attributes['avg_rating'] ?? 0), 1));
    }

    protected function reviewCount(): Attribute
    {
        return Attribute::get(fn (): int => (int) ($this->attributes['reviews_count'] ?? 0));
    }

    protected function loanCount(): Attribute
    {
        return Attribute::get(fn (): int => (int) ($this->attributes['loans_count'] ?? 0));
    }

    protected function coverTone(): Attribute
    {
        return Attribute::get(fn (): int => $this->category_id ? $this->category_id % 8 : 6);
    }

    // ------------------------------------------------------------------- scopes

    /** Menambahkan rating rata-rata, jumlah ulasan, dan jumlah peminjaman dalam satu query. */
    public function scopeWithStats(Builder $query): void
    {
        $query->withAvg('reviews as avg_rating', 'rating')->withCount(['reviews', 'loans']);
    }

    public function scopeFilter(Builder $query, CatalogFilters $f, bool $withCategory = true): void
    {
        $query
            ->when($f->q !== '', function (Builder $q) use ($f) {
                $like = (new SearchTerm($f->q))->like();
                $q->where(fn (Builder $w) => $w
                    ->where('title', 'ilike', $like)
                    ->orWhere('author', 'ilike', $like)
                    ->orWhere('isbn', 'ilike', $like)
                    ->orWhere('publisher', 'ilike', $like));
            })
            ->when($withCategory && $f->category !== '', fn (Builder $q) => $q->whereHas('category', fn (Builder $c) => $c->where('slug', $f->category)))
            ->when($f->yearFrom, fn (Builder $q, int $y) => $q->where('published_year', '>=', $y))
            ->when($f->yearTo, fn (Builder $q, int $y) => $q->where('published_year', '<=', $y))
            ->when($f->language !== '', fn (Builder $q) => $q->where('language', $f->language))
            ->when($f->digital, fn (Builder $q) => $q->whereNotNull('pdf_path'));
    }

    public function scopeSortedBy(Builder $query, CatalogSort $sort, string $q = ''): void
    {
        $term = new SearchTerm($q);

        match ($sort) {
            CatalogSort::Title => $query->orderByRaw('lower(title)'),
            CatalogSort::Rating => $query->orderByRaw('avg_rating DESC NULLS LAST')->orderByDesc('reviews_count')->orderBy('title'),
            CatalogSort::Year => $query->orderByRaw('published_year DESC NULLS LAST')->orderBy('title'),
            CatalogSort::Popular => $query->orderByDesc('loans_count')->orderByRaw('avg_rating DESC NULLS LAST')->orderBy('title'),
            CatalogSort::Relevance => $q !== ''
                ? $query->orderByRaw(
                    'CASE WHEN title ILIKE ? THEN 0 WHEN title ILIKE ? THEN 1 WHEN author ILIKE ? THEN 2 ELSE 3 END',
                    [$term->startsWith(), $term->like(), $term->like()]
                )->orderByRaw('lower(title)')
                : $query->latest()->orderByDesc('id'),
            default => $query->latest()->orderByDesc('id'),
        };
    }
}
