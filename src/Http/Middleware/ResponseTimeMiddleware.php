<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\{Request, Response};

/**
 * Response Time Middleware
 *
 * İsteğin başlangıcından sonuna kadar geçen süreyi (ms) hesaplar
 * ve 'X-Response-Time' başlığı olarak yanıta ekler.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class ResponseTimeMiddleware implements MiddlewareInterface
{
    /**
     * İsteği zamanlayarak işle.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Zamanlayıcıyı başlat
        $start = microtime(true);

        // 2. İsteği bir sonraki katmana (ve en son controller'a) gönder
        $response = $next($request);

        // 3. Geçen süreyi milisaniye (ms) olarak hesapla
        $duration = (microtime(true) - $start) * 1000;

        // 4. Yanıta (Response) başlığı (header) ekle
        $response->header('X-Response-Time', round($duration, 2) . 'ms');

        return $response;
    }
}