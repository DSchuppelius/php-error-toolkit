<?php
/*
 * Created on   : Thu Apr 03 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoggerRegistry.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit;

use Closure;
use Psr\Log\LoggerInterface;

/**
 * Process-global logger registry.
 *
 * WARNING: setLogger()/setLoggerResolver() install PROCESS-GLOBAL state. In a
 * shared-nothing SAPI (mod_php, FPM) that is fine, but in long-running,
 * potentially multi-tenant runtimes (Laravel Octane, Swoole, RoadRunner, queue
 * workers) a logger set for one request/tenant persists into the next one on
 * the same worker and can route another tenant's log records to the wrong sink.
 *
 * For those runtimes prefer {@see setLoggerResolver()} bound to the current
 * request/container, and call {@see resetLogger()} at each request boundary
 * (the Laravel bridge does this in boot()). Do not use setLogger() to route
 * logs per tenant inside a shared worker.
 */
class LoggerRegistry {
    private static ?LoggerInterface $logger = null;
    private static ?Closure $resolver = null;

    public static function setLogger(LoggerInterface $logger): void {
        self::$logger = $logger;
    }

    /**
     * Register a lazy logger factory instead of a concrete instance.
     *
     * The resolver is invoked once, on first getLogger() call without an
     * explicitly set logger. Frameworks use this to defer resolution until
     * their container is ready (see the Laravel bridge); an explicitly set
     * logger always wins.
     *
     * @param Closure(): ?LoggerInterface $resolver
     */
    public static function setLoggerResolver(?Closure $resolver): void {
        self::$resolver = $resolver;
    }

    public static function getLogger(): ?LoggerInterface {
        // An explicitly set logger (setLogger) wins and is returned as-is —
        // the caller owns its lifecycle.
        if (self::$logger !== null) {
            return self::$logger;
        }

        if (self::$resolver === null) {
            return null;
        }

        // Resolver results are DELIBERATELY NOT cached in self::$logger:
        // caching would freeze a logger bound to the application instance that
        // happened to be current on the first call. That instance may already
        // be flushed on the next call (a plain PHPUnit test after a feature
        // test in the same process, an Octane worker, a queue restart) — using
        // the cached logger then resolves channels/config against a dead
        // container ("Class 'log' does not exist" / "Target class [config]").
        // Re-resolving on every call is cheap (the container returns its
        // singletons) and always targets the CURRENT container. A resolver
        // that still throws must never take logging — and thus the process —
        // down, so we fail soft to null (ErrorLog falls back to STDERR/syslog).
        try {
            $resolved = (self::$resolver)();
        } catch (\Throwable) {
            return null;
        }

        return $resolved instanceof LoggerInterface ? $resolved : null;
    }

    public static function resetLogger(): void {
        self::$logger = null;
        self::$resolver = null;
    }

    public static function hasLogger(): bool {
        return !is_null(self::getLogger());
    }
}
