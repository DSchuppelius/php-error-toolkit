<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConsoleLogger.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Logger;

use ERRORToolkit\Contracts\Abstracts\LoggerAbstract;
use Psr\Log\LogLevel;

class ConsoleLogger extends LoggerAbstract {

    protected array $levelColors = [
        'emergency' => "\033[1;31m", // Rot
        'alert'     => "\033[1;31m", // Rot
        'critical'  => "\033[1;35m", // Magenta
        'error'     => "\033[1;31m", // Rot
        'warning'   => "\033[1;33m", // Gelb
        'notice'    => "\033[1;34m", // Blau
        'info'      => "\033[0;32m", // Grün
        'debug'     => "\033[0;36m", // Cyan
    ];

    protected string $resetColor = "\033[0m"; // Zurücksetzen auf Standard

    public function __construct(string $logLevel = LogLevel::DEBUG) {
        parent::__construct($logLevel);
    }

    protected function writeLog(string $logEntry, string $level): void {
        $color = $this->levelColors[strtolower($level)] ?? $this->resetColor;

        echo PHP_EOL . $color . $logEntry . $this->resetColor;
    }

    public function log($level, string|\Stringable $message, array $context = []): void {
        if ($this->shouldLog($level)) {
            $logEntry = $this->generateLogEntry($level, $message, $context);

            $this->writeLog($logEntry, $level);
        }
    }
}
