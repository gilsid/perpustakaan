<?php

namespace App\Support;

/**
 * Keadaan anggota terhadap peminjaman: alasan yang menghalangi, jumlah
 * pinjaman berjalan, dan batasnya. Halaman buku dan rak saya menerima hasil
 * ini, bukan menghitung ulang aturannya.
 */
final readonly class BorrowStanding
{
    /**
     * @param  list<BorrowBlock>  $blocks
     */
    public function __construct(
        public array $blocks,
        public int $activeLoans,
        public int $maxActive,
    ) {}

    public function isBlocked(): bool
    {
        return $this->blocks !== [];
    }

    /** Alasan pertama, satu-satunya yang ditampilkan saat peminjaman ditolak. */
    public function first(): ?BorrowBlock
    {
        return $this->blocks[0] ?? null;
    }
}
