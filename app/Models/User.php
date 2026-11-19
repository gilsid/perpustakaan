<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'suspend_until' => 'datetime',
        ];
    }

    /** Email selalu disimpan huruf kecil agar pencarian dan keunikan tidak peka huruf besar/kecil. */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim($value));
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isMember(): bool
    {
        return $this->role === UserRole::Member;
    }

    /** @return HasMany<Loan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isSuspended(): bool
    {
        // getRawOriginal tidak melempar galat saat kolom belum termuat (mis. model
        // yang baru dibuat lewat factory), berbeda dengan akses atribut biasa.
        $until = $this->getRawOriginal('suspend_until');

        return $until !== null && $this->asDateTime($until)->isFuture();
    }

    /** @return BelongsToMany<Book, $this> */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites')->withTimestamps();
    }
}
