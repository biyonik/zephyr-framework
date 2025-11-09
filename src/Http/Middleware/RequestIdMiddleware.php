<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\{Request, Response};

/**
 * Request ID Middleware
 *
 * Her isteğe benzersiz bir kimlik (ID) atar.
 * Bu ID, log takibi ve hata ayıklama (debugging) için kullanılır.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class RequestIdMiddleware implements MiddlewareInterface
{
    /**
     * İsteğe kimlik atar.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?? 'zephyr_' . bin2hex(random_bytes(10));

        $request->headers['X-Request-Id'] = $requestId;

        // 3. İsteği bir sonraki katmana gönder
        $response = $next($request);

        // 4. Aynı ID'yi yanıta (Response) da ekle
        // Böylece istemci (client) bu ID'yi loglayabilir
        $response->header('X-Request-Id', $requestId);

        return $response;
    }
}