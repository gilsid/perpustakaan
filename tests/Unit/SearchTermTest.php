<?php

namespace Tests\Unit;

use App\Support\SearchTerm;
use PHPUnit\Framework\TestCase;

class SearchTermTest extends TestCase
{
    public function test_the_pattern_matches_anywhere(): void
    {
        $this->assertSame('%budi%', (new SearchTerm('budi'))->like());
    }

    public function test_the_prefix_pattern_matches_the_start(): void
    {
        $this->assertSame('budi%', (new SearchTerm('budi'))->startsWith());
    }

    public function test_wildcards_are_escaped(): void
    {
        $this->assertSame('%100\%\_%', (new SearchTerm('100%_'))->like());
        $this->assertSame('\%%', (new SearchTerm('%'))->startsWith());
        $this->assertSame('%a\\\\b%', (new SearchTerm('a\b'))->like());
    }

    public function test_an_empty_term_is_blank(): void
    {
        $this->assertTrue((new SearchTerm(''))->isBlank());
        $this->assertFalse((new SearchTerm('x'))->isBlank());
    }
}
