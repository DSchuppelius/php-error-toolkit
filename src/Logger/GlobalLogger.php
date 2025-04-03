<?php
/*
 * Created on   : Thu Apr 03 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalLogger.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Logger;

use Psr\Log\LoggerInterface;
use ERRORToolkit\Traits\ErrorLog;

class GlobalLogger {
    use ErrorLog;

    public static function init(LoggerInterface $logger): void {
        self::setLogger($logger);
    }
}
