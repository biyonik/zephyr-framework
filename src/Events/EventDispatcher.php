<?php

declare(strict_types=1);

namespace Zephyr\Events;

use Zephyr\Core\App;

/**
 * Event Dispatcher (Olay Yönlendirici)
 *
 * Olayları (Events) alır ve kayıtlı dinleyicilere (Listeners) dağıtır.
 *
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class EventDispatcher
{
    protected App $app;

    /**
     * Olay -> Dinleyici eşleşmeleri.
     * config/events.php dosyasından doldurulur.
     *
     * @var array<string, array<string>>
     */
    protected array $listeners = [];

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Bir olaya (event) bir dinleyici (listener) kaydeder.
     * Genellikle EventServiceProvider tarafından 'boot' aşamasında kullanılır.
     *
     * @param string $event (Event sınıfının tam adı)
     * @param string $listener (Listener sınıfının tam adı)
     */
    public function listen(string $event, string $listener): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $listener;
    }

    /**
     * Bir olayı (event) tetikler ve tüm dinleyicilerine dağıtır.
     *
     * @param object $event Tetiklenen olay nesnesi
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);

        if (!isset($this->listeners[$eventName])) {
            // Bu olayı dinleyen kimse yok
            return;
        }

        foreach ($this->listeners[$eventName] as $listenerClass) {
            try {
                // 1. Dinleyiciyi container'dan çöz
                // (Böylece listener'ın constructor'ına başka servisler enjekte edilebilir)
                $listener = $this->app->resolve($listenerClass);

                // 2. Dinleyicinin 'handle' metodunu olay (event) ile çağır
                if (method_exists($listener, 'handle')) {
                    $listener->handle($event);
                } else {
                    log()->warning("Listener [{$listenerClass}] 'handle' metoduna sahip değil.");
                }

            } catch (\Throwable $e) {
                // 3. Hata Yönetimi
                // Bir dinleyici hata verirse, diğerlerinin çalışmasını engellememeli.
                // Hatayı yeni loglama sistemimizle kaydedelim.
                log()->error("Event listener hatası [{$listenerClass}]: " . $e->getMessage(), [
                    'event' => $eventName,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}