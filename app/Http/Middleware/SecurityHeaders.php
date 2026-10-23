<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Header yang sudah diatur route/respons (mis. CSP khusus pembaca PDF) tidak ditimpa.
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
        ];
        foreach ($defaults as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        // Saat `npm run dev`, Vite menyajikan aset dari server HMR-nya sendiri.
        $dev = Vite::isRunningHot() ? ' '.trim((string) file_get_contents(public_path('hot'))).' ws://localhost:* ws://[::1]:*' : '';

        return implode('; ', [
            "default-src 'self'",
            "img-src 'self' data:",
            "style-src 'self'".$dev,
            "font-src 'self' data:".$dev,
            "script-src 'self'".$dev,
            "connect-src 'self'".$dev,
            "frame-src 'self'",
            "object-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
