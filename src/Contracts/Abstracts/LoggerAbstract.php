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
        $this->logLevel = $this->convertLogLevel($logLevel);
    }

    private function convertLogLevel(string $logLevel): int {
        $levels = [
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT => 1,
            LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3,
            LogLevel::WARNING => 4,
            LogLevel::NOTICE => 5,
            LogLevel::INFO => 6,
            LogLevel::DEBUG => 7,
        ];

        if (!isset($levels[$logLevel])) {
            throw new InvalidArgumentException("Ungültiges LogLevel: {$logLevel}");
        }

        return $levels[$logLevel];
    }

    private function getCallerFunction(): string {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($backtrace as $trace) {
            if (isset($trace['class']) && !str_starts_with($trace['class'], 'ERRORToolkit')) {
                return "{$trace['class']}::{$trace['function']}()";
            }
        }

        return 'Unbekannt';
    }

    protected function shouldLog(string $level): bool {
        return $this->convertLogLevel($level) <= $this->logLevel;
    }

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

    public function generateLogEntry($level, string|\Stringable $message, array $context = []): string {
        $timestamp = date('Y-m-d H:i:s');
        $caller = $this->getCallerFunction();
        $contextString = empty($context) ? "" : " " . json_encode($context);
        return "[{$timestamp}] {$level} [{$caller}]: {$message}{$contextString}";
    }

    abstract protected function writeLog(string $logEntry, string $level): void;

    public function log($level, string|\Stringable $message, array $context = []): void {
        if ($this->shouldLog($level)) {
            $logEntry = $this->generateLogEntry($level, $message, $context);
            $this->writeLog($logEntry, $level);
        }
    }
}