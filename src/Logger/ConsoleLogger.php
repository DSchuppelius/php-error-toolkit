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

class ConsoleLogger extends LoggerAbstract {

    private const LEVEL_COLORS = [
        'emergency' => "\033[1;31m", // Rot
        'alert'     => "\033[1;31m", // Rot
        'critical'  => "\033[1;35m", // Magenta
        'error'     => "\033[1;31m", // Rot
        'warning'   => "\033[1;33m", // Gelb
        'notice'    => "\033[1;34m", // Blau
        'info'      => "\033[0;32m", // Grün
        'debug'     => "\033[0;36m", // Cyan
    ];

    private const RESET_COLOR = "\033[0m"; // Zurücksetzen auf Standard

    /** @var resource */
    private $stream;

    /**
     * @param resource|null $stream Output-Stream (Standard: STDERR)
     */
    public function __construct(string $logLevel = LogLevel::DEBUG, bool $enableDeduplication = true, $stream = null) {
        parent::__construct($logLevel, $enableDeduplication);
        $this->stream = $stream ?? STDERR;
    }

    protected function writeLog(string $logEntry, string $level): void {
        if (TerminalHelper::supportsColors()) {
            $color = self::LEVEL_COLORS[strtolower($level)] ?? self::RESET_COLOR;
            $output = $color . $logEntry . self::RESET_COLOR;
        } else {
            $output = $logEntry;
        }

        fwrite($this->stream, $output . "\n");
    }
}
