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
        if (self::$logger === null && self::$resolver !== null) {
            $resolved = (self::$resolver)();
            if ($resolved instanceof LoggerInterface) {
                self::$logger = $resolved;
            }
        }

        return self::$logger;
    }

    public static function resetLogger(): void {
        self::$logger = null;
        self::$resolver = null;
    }

    public static function hasLogger(): bool {
        return !is_null(self::getLogger());
    }
}
