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
use ERRORToolkit\Helper\TerminalHelper;
use Psr\Log\LogLevel;
use Stringable;

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
        $output = $color . $logEntry . $this->resetColor;
        if (TerminalHelper::isTerminal()) {
            if (TerminalHelper::getCursorColumn() > 0) {
                echo PHP_EOL;
            }

            echo $output . PHP_EOL;
        } else {
            echo PHP_EOL . $output;
        }
    }

    public function log($level, string|Stringable $message, array $context = []): void {
        if ($this->shouldLog($level)) {
            $logEntry = $this->generateLogEntry($level, $message, $context);

            $this->writeLog($logEntry, $level);
        }
    }
}
