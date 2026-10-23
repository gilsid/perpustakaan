<?php

namespace Tests\Unit;

use App\Enums\CatalogSort;
use App\Support\CatalogFilters;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class CatalogFiltersTest extends TestCase
{
    private function filters(array $query): CatalogFilters
    {
        return CatalogFilters::fromRequest(Request::create('/katalog', 'GET', $query));
    }

    public function test_defaults_to_newest_without_a_search_term(): void
    {
        $f = $this->filters([]);

        $this->assertSame(CatalogSort::Newest, $f->sort);
        $this->assertFalse($f->hasActiveFilters());
        $this->assertSame([], $f->toQuery());
    }

    public function test_defaults_to_relevance_when_searching(): void
    {
        $f = $this->filters(['q' => '  clean  ']);

        $this->assertSame('clean', $f->q);
        $this->assertSame(CatalogSort::Relevance, $f->sort);
        $this->assertSame(['q' => 'clean'], $f->toQuery(), 'urutan bawaan tidak ikut ke URL');
    }

    public function test_relevance_without_a_search_term_falls_back_to_newest(): void
    {
        $this->assertSame(CatalogSort::Newest, $this->filters(['urut' => 'relevan'])->sort);
    }

    public function test_years_outside_the_column_range_are_ignored(): void
    {
        $f = $this->filters(['dari' => '99999999999999999999', 'sampai' => '12']);

        $this->assertNull($f->yearFrom);
        $this->assertNull($f->yearTo);
        $this->assertSame(1990, $this->filters(['dari' => '1990'])->yearFrom);
    }

    public function test_malformed_values_are_ignored_instead_of_failing(): void
    {
        $f = $this->filters(['q' => ['x'], 'urut' => ['x'], 'dari' => ['1'], 'kategori' => ['a'], 'digital' => ['1'], 'bahasa' => ['x']]);

        $this->assertSame('', $f->q);
        $this->assertSame('', $f->category);
        $this->assertFalse($f->digital);
        $this->assertSame(CatalogSort::Newest, $f->sort);
    }

    public function test_long_search_terms_are_truncated(): void
    {
        $this->assertSame(100, mb_strlen($this->filters(['q' => str_repeat('a', 500)])->q));
    }

    public function test_to_query_uses_url_parameter_names(): void
    {
        $f = $this->filters(['kategori' => 'fiksi', 'dari' => '2000', 'sampai' => '2020', 'bahasa' => 'Indonesia', 'digital' => '1', 'urut' => 'judul']);

        $this->assertSame(
            ['kategori' => 'fiksi', 'dari' => 2000, 'sampai' => 2020, 'bahasa' => 'Indonesia', 'digital' => '1', 'urut' => 'judul'],
            $f->toQuery(),
        );
    }
}
