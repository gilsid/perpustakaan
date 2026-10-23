<?php

namespace App\Support;

use App\Enums\CatalogSort;
use Illuminate\Http\Request;

/** Filter katalog yang sudah dibersihkan dari URL. */
final readonly class CatalogFilters
{
    public function __construct(
        public string $q = '',
        public string $category = '',
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public string $language = '',
        public bool $digital = false,
        public CatalogSort $sort = CatalogSort::Newest,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $q = Query::string($request, 'q');
        $sort = Query::enum($request, 'urut', CatalogSort::class) ?? ($q !== '' ? CatalogSort::Relevance : CatalogSort::Newest);
        if ($sort === CatalogSort::Relevance && $q === '') {
            $sort = CatalogSort::Newest;
        }

        return new self(
            q: $q,
            category: Query::string($request, 'kategori'),
            yearFrom: self::year(Query::int($request, 'dari')),
            yearTo: self::year(Query::int($request, 'sampai')),
            language: Query::string($request, 'bahasa'),
            digital: Query::string($request, 'digital') === '1',
            sort: $sort,
        );
    }

    /** Tahun di luar rentang kolom (smallint, 1000–2200) diabaikan agar tidak memicu galat database. */
    private static function year(?int $year): ?int
    {
        return $year !== null && $year >= 1000 && $year <= 2200 ? $year : null;
    }

    public function defaultSort(): CatalogSort
    {
        return $this->q !== '' ? CatalogSort::Relevance : CatalogSort::Newest;
    }

    /** Parameter URL yang sedang berlaku (nama URL berbeda dari nama properti). */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->q,
            'kategori' => $this->category,
            'dari' => $this->yearFrom,
            'sampai' => $this->yearTo,
            'bahasa' => $this->language,
            'digital' => $this->digital ? '1' : null,
            'urut' => $this->sort !== $this->defaultSort() ? $this->sort->value : null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    public function hasActiveFilters(): bool
    {
        return $this->q !== '' || $this->category !== '' || $this->yearFrom || $this->yearTo || $this->language !== '' || $this->digital;
    }
}
