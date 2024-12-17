<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConsoleLoggerFactory.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Factories;

use ERRORToolkit\Contracts\Interfaces\LoggerFactoryInterface;
use ERRORToolkit\Logger\ConsoleLogger;
use Psr\Log\LoggerInterface;

class ConsoleLoggerFactory implements LoggerFactoryInterface {
    protected static ?LoggerInterface $logger = null;

    public static function getLogger(): LoggerInterface {
        if (self::$logger === null) {
            self::$logger = new ConsoleLogger();
        }
        return self::$logger;
    }
}
