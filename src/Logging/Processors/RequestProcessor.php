<?php

declare(strict_types=1);

namespace Zephyr\Logging\Processors;

use Zephyr\Core\App;
use Zephyr\Http\Request;

/**
 * RequestProcessor
 *
 * Her log kaydına 'X-Request-Id' başlığını 'extra' olarak ekler.
 * Bu, logları bir istek (request) bazında filtrelemeyi sağlar.
 */
class RequestProcessor
{
    public function __construct(private App $app)
    {
    }

    /**
     * Monolog Processor'ın çalıştıracağı ana metot.
     *
     * @param array $record Log kaydı
     * @return array Güncellenmiş log kaydı
     */
    public function __invoke(array $record): array
    {
        // Sadece HTTP context'inde çalışıyorsak (CLI komutu değilse)
        // ve Request nesnesi container'a kaydedilmişse
        if (PHP_SAPI !== 'cli' && $this->app->has(Request::class)) {

            $request = $this->app->resolve(Request::class);
            $requestId = $request->header('X-Request-Id');

            if ($requestId) {
                // Log kaydının 'extra' bölümüne request_id'yi ekle
                $record['extra']['request_id'] = $requestId;
            }
        }

        return $record;
    }
}