<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name'])]
class Category extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Slug dibuat dari nama dan dijamin unik: "fiksi" → "fiksi-2" bila sudah dipakai.
        static::saving(function (Category $category): void {
            if ($category->isDirty('name') || blank($category->slug)) {
                $category->slug = static::uniqueSlug($category->name, $category->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        for ($i = 2; static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** @return HasMany<Book, $this> */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /** Indeks warna 0–7 yang dipakai laci kategori dan sampul otomatis. */
    public function tone(): int
    {
        return $this->id % 8;
    }
}
