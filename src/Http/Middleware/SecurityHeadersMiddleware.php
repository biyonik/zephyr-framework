<?php

declare(strict_types=1);

namespace Zephyr\Http\Middleware;

use Closure;
use Zephyr\Http\{Request, Response};

/**
 * Security Headers Middleware
 *
 * Tarayıcı tabanlı güvenlik açıklarını (Clickjacking, MIME-sniffing)
 * azaltmak için her yanıta standart güvenlik başlıkları ekler.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Eklenecek güvenlik başlıkları.
     *
     * @var array<string, string>
     */
    protected array $headers = [
        // MIME tipini 'sniff' etmeyi engeller (örn: 'text/plain' dosyasını 'script' olarak çalıştırmayı dener)
        'X-Content-Type-Options' => 'nosniff',

        // Sitenin bir 'iframe' içinde (örn: başka sitede)
        // gömülmesini engeller (Clickjacking koruması)
        'X-Frame-Options' => 'SAMEORIGIN',

        // Tarayıcıya, sitenin sadece HTTPS üzerinden erişilmesi
        // gerektiğini bildirir.
        // DİKKAT: Bunu üretimde (production) etkinleştirmeden önce
        // sitenizin SSL sertifikasının tam olarak çalıştığından emin olun.
        // Yanlış yapılandırma, sitenize erişimi tamamen engelleyebilir.
        // 'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',

        // Ne kadar 'Referer' bilgisi gönderileceğini kontrol eder (gizlilik)
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * Gelen isteği işle ve yanıta başlıkları ekle.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Önce isteğin işlenmesini bekle
        $response = $next($request);

        // Şimdi yanıta (response) güvenlik başlıklarını ekle
        foreach ($this->headers as $key => $value) {
            if (!$response->getHeaders()->has($key)) {
                $response->header($key, $value);
            }
        }

        return $response;
    }
}