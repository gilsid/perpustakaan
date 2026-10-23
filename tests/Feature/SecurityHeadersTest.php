<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_carry_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_csp_does_not_allow_external_hosts_for_fonts_or_styles(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('googleapis', $csp);
        $this->assertStringNotContainsString('gstatic', $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
    }

    public function test_pages_do_not_load_anything_from_third_parties(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertDoesNotMatchRegularExpression('~https?://(?!localhost|127\.0\.0\.1)[^"\']*\.(css|js|woff2?)~i', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }

    public function test_pages_contain_no_inline_scripts_or_styles_that_the_csp_would_block(): void
    {
        $book = Book::factory()->create();
        $paths = ['/', '/katalog', '/kategori', "/buku/{$book->id}", '/masuk', '/daftar'];

        foreach ($paths as $path) {
            $html = $this->get($path)->getContent();
            $this->assertDoesNotMatchRegularExpression('~<script(?![^>]*\ssrc=)[^>]*>~i', $html, "script inline di $path");
            $this->assertDoesNotMatchRegularExpression('~\sstyle="~i', str_replace('class="sprite"', '', $html), "atribut style inline di $path");
        }
    }

    public function test_error_pages_render_inside_the_site_layout(): void
    {
        $this->get('/halaman-yang-tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan')->assertSee('Ke beranda');
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
