<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoggerAbstract.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Contracts\Abstracts;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use InvalidArgumentException;

abstract class LoggerAbstract implements LoggerInterface {
    protected int $logLevel;

    public function __construct(string $logLevel = LogLevel::DEBUG) {
        $this->setLogLevel($logLevel);
    }

    public function setLogLevel(string $logLevel): void {
        $this->logLevel = self::convertLogLevel($logLevel);
    }

    private static function convertLogLevel(string $logLevel): int {
        static $levels = [
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT     => 1,
            LogLevel::CRITICAL  => 2,
            LogLevel::ERROR     => 3,
            LogLevel::WARNING   => 4,
            LogLevel::NOTICE    => 5,
            LogLevel::INFO      => 6,
            LogLevel::DEBUG     => 7,
        ];

        return $levels[$logLevel] ?? throw new InvalidArgumentException("Ungültiges LogLevel: {$logLevel}");
    }

    private static function getCallerFunction(int $logLevel = 0): string {
        $ignoreFunctions = ['logInternal', '__call', '__callStatic'];
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach (array_slice($backtrace, 1) as $trace) {
            if (
                isset($trace['class'], $trace['function']) &&
                !str_starts_with($trace['class'], 'ERRORToolkit') &&
                !in_array($trace['function'], $ignoreFunctions, true)
            ) {
                if ($logLevel === 7) {
                    $file = $trace['file'] ?? 'unknown';
                    $line = $trace['line'] ?? 0;
                    return "{$trace['class']}::{$trace['function']}() in {$file}:{$line}";
                }
                return "{$trace['class']}::{$trace['function']}()";
            }
        }
        return 'Unbekannt';
    }


    protected function shouldLog(string $level): bool {
        return self::convertLogLevel($level) <= $this->logLevel;
    }

    public function log($level, string|\Stringable $message, array $context = []): void {
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = $this->generateLogEntry($level, $message, $context);
        $this->writeLog($logEntry, $level);
    }

    public function generateLogEntry($level, string|\Stringable $message, array $context = []): string {
        $timestamp = date('Y-m-d H:i:s');
        $caller = self::getCallerFunction($this->logLevel);
        $contextString = empty($context) ? "" : " " . json_encode($context);
        return "[{$timestamp}] {$level} [{$caller}]: {$message}{$contextString}";
    }

    abstract protected function writeLog(string $logEntry, string $level): void;

    public function emergency(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }
    public function alert(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::ALERT, $message, $context);
    }
    public function critical(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }
    public function error(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::ERROR, $message, $context);
    }
    public function warning(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::WARNING, $message, $context);
    }
    public function notice(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::NOTICE, $message, $context);
    }
    public function info(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::INFO, $message, $context);
    }
    public function debug(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
}
