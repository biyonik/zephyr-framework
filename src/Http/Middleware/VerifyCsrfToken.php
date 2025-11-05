<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\{Request, Response};
use Zephyr\Exceptions\Http\ForbiddenException; //

/**
 * CSRF (Cross-Site Request Forgery) Koruması
 *
 * "Double Submit Cookie" desenini kullanarak state-changing
 * (POST, PUT, DELETE, PATCH) istekleri doğrular.
 *
 * Rapor #4'e (report.md) istinaden eklendi.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * CSRF korumasından muaf tutulacak URI'lar.
     * (örn. webhook'lar)
     *
     * @var array<string>
     */
    protected array $except = [
        // 'api/webhook/stripe',
    ];

    /**
     * CSRF token'ını taşıyan Header'ın adı.
     */
    protected string $headerName = 'X-CSRF-TOKEN';

    /**
     * CSRF token'ını taşıyan Cookie'nin adı.
     */
    protected string $cookieName = 'XSRF-TOKEN';

    /**
     * Gelen isteği doğrula.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     *
     * @throws ForbiddenException
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Okuma amaçlı (safe) metodları veya muaf listesindekileri
        // her zaman kabul et.
        if ($this->isReading($request) || $this->inExceptArray($request)) {
            return $next($request);
        }

        // Token'ları doğrula
        if ($this->tokensMatch($request)) {
            return $next($request);
        }
        
        // Eşleşme yoksa veya token'lar eksikse reddet
        throw new ForbiddenException('CSRF token mismatch.');
    }

    /**
     * İsteğin "okuma" amaçlı (GET, HEAD, OPTIONS) olup olmadığını kontrol et.
     */
    protected function isReading(Request $request): bool
    {
        return in_array($request->method(), ['HEAD', 'GET', 'OPTIONS'], true);
    }

    /**
     * URI'ın muaf listesinde olup olmadığını kontrol et.
     */
    protected function inExceptArray(Request $request): bool
    {
        foreach ($this->except as $except) {
            // Basit wildcard (*) desteği
            $except = str_replace('*', '.*', $except);
            if (preg_match("#^{$except}$#", $request->path())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Header'daki token ile Cookie'deki token'ın eşleşip eşleşmediğini kontrol et.
     */
    protected function tokensMatch(Request $request): bool
    {
        // 1. Header'dan veya (form body'sinden) token'ı al
        $token = $request->input('_token') ?? $request->header($this->headerName); //

        // 2. Cookie'den token'ı al
        $cookieToken = $request->cookie($this->cookieName); //

        if (!$token || !$cookieToken) {
            return false;
        }

        // 3. Güvenli karşılaştırma (Timing Attack Koruması)
        return hash_equals($cookieToken, $token);
    }
}