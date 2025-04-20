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

use Psr\Log\LoggerInterface;

class LoggerRegistry {
    private static ?LoggerInterface $logger = null;

    public static function setLogger(LoggerInterface $logger): void {
        self::$logger = $logger;
    }

    public static function getLogger(): ?LoggerInterface {
        return self::$logger;
    }

    public static function resetLogger(): void {
        self::$logger = null;
    }

    public static function hasLogger(): bool {
        return !is_null(self::$logger);
    }
}
