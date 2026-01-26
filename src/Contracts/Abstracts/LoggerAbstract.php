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
use Stringable;

abstract class LoggerAbstract implements LoggerInterface {
    protected int $logLevel;

    /**
     * Deduplizierung: Verhindert doppelte aufeinanderfolgende Log-Einträge.
     */
    protected bool $deduplicationEnabled = true;
    protected ?string $lastLogKey = null;
    protected int $duplicateCount = 0;
    protected ?string $lastLevel = null;
    protected ?string $lastMessage = null;
    protected array $lastContext = [];

    public function __construct(string $logLevel = LogLevel::DEBUG, bool $enableDeduplication = true) {
        $this->setLogLevel($logLevel);
        $this->deduplicationEnabled = $enableDeduplication;
    }

    /**
     * Aktiviert oder deaktiviert die Deduplizierung von Log-Einträgen.
     * 
     * @param bool $enabled True um Deduplizierung zu aktivieren
     */
    public function setDeduplication(bool $enabled): void {
        if (!$enabled && $this->deduplicationEnabled) {
            // Vor dem Deaktivieren ausstehende Duplikate ausgeben
            $this->flushDuplicates();
        }
        $this->deduplicationEnabled = $enabled;
    }

    /**
     * Prüft, ob Deduplizierung aktiviert ist.
     */
    public function isDeduplicationEnabled(): bool {
        return $this->deduplicationEnabled;
    }

    /**
     * Gibt ausstehende Log-Einträge aus.
     * Sollte am Ende einer Log-Session aufgerufen werden.
     */
    public function flushDuplicates(): void {
        if ($this->lastLogKey !== null && $this->lastLevel !== null && $this->lastMessage !== null) {
            $this->writePendingEntry();
        }
        $this->resetDeduplicationState();
    }

    /**
     * Setzt den Deduplizierungs-Status zurück.
     */
    protected function resetDeduplicationState(): void {
        $this->lastLogKey = null;
        $this->duplicateCount = 0;
        $this->lastLevel = null;
        $this->lastMessage = null;
        $this->lastContext = [];
    }

    /**
     * Schreibt den ausstehenden Eintrag (mit Zähler falls Duplikate vorhanden).
     */
    protected function writePendingEntry(): void {
        $message = $this->lastMessage;
        if ($this->duplicateCount > 0) {
            $count = $this->duplicateCount + 1; // +1 weil das erste Auftreten nicht gezählt wird
            $message .= " (x{$count})";
        }
        $logEntry = $this->generateLogEntry($this->lastLevel, $message, $this->lastContext);
        $this->writeLog($logEntry, $this->lastLevel);
    }

    /**
     * Erzeugt einen eindeutigen Schlüssel für einen Log-Eintrag zur Deduplizierung.
     */
    protected function createLogKey(string $level, string|Stringable $message, array $context): string {
        return md5($level . '|' . (string)$message . '|' . json_encode($context));
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

    /**
     * Liste der internen Trait/Logger-Methoden, die im Backtrace übersprungen werden sollen.
     * Diese Liste wird sowohl von LoggerAbstract als auch vom ErrorLog Trait verwendet.
     */
    public static array $internalMethods = [
        // ErrorLog Trait Methoden
        'logInternal',
        'handleMagicCall',
        'handleConditionalLog',
        'handleLogAndReturn',
        'handleLogWithTimer',
        'handleStandardLog',
        'doLogAndThrow',
        'logErrorAndThrow',
        'logCriticalAndThrow',
        'logAlertAndThrow',
        'logEmergencyAndThrow',
        'logException',
        '__call',
        '__callStatic',
        'getExternalCaller',
        'createDebugContext',
        // Logger Methoden
        'log',
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
        'generateLogEntry',
        'getCallerFunction',
        'writeLog',
    ];

    /**
     * Ermittelt den ersten externen Caller außerhalb des ERRORToolkit.
     * Überspringt alle internen Trait/Logger-Methoden im Backtrace.
     * Funktioniert auch bei Script-Aufrufen ohne Klassen-Kontext.
     * 
     * Bei Backtraces gilt:
     * - file/line zeigt WO die Funktion aufgerufen wurde
     * - function/class zeigt WELCHE Funktion aufgerufen wurde
     * 
     * @param int $additionalSkip Zusätzliche Frames, die übersprungen werden sollen
     * @return array{file: string, line: int, function: string, class: string|null}
     */
    public static function getExternalCaller(int $additionalSkip = 0): array {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        $defaultCaller = [
            'file' => 'unknown',
            'line' => 0,
            'function' => '{script}',
            'class' => null,
        ];

        // Finde den ERSTEN externen Frame (nicht den letzten internen!)
        // Der erste Frame der NICHT intern ist UND NICHT zum ERRORToolkit gehört,
        // ist der echte Caller der logInfo() etc. aufgerufen hat.

        foreach ($backtrace as $index => $frame) {
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? null;

            // Überspringe interne Methoden
            if (in_array($function, self::$internalMethods, true)) {
                continue;
            }

            // Überspringe ERRORToolkit Namespace
            if ($class !== null && str_starts_with($class, 'ERRORToolkit')) {
                continue;
            }

            // Wende additionalSkip an
            if ($additionalSkip > 0) {
                $additionalSkip--;
                continue;
            }

            // Das ist der erste externe Caller!
            // file/line kommen vom VORHERIGEN Frame (wo der Aufruf stattfand)
            $prevFrame = $backtrace[$index - 1] ?? null;

            return [
                'file' => $prevFrame['file'] ?? $frame['file'] ?? $defaultCaller['file'],
                'line' => $prevFrame['line'] ?? $frame['line'] ?? 0,
                'function' => $function,
                'class' => $class,
            ];
        }

        // Fallback: Script-Aufruf
        return $defaultCaller;
    }

    /**
     * Formatiert die Caller-Information als String für Log-Einträge.
     * 
     * @param bool $includeFileInfo Ob Datei und Zeilennummer inkludiert werden sollen
     * @return string Formatierter Caller-String
     */
    private static function getCallerFunction(bool $includeFileInfo = false): string {
        $caller = self::getExternalCaller();

        if ($caller['class'] !== null) {
            $result = "{$caller['class']}::{$caller['function']}()";
        } else {
            $result = $caller['function'];
        }

        if ($includeFileInfo && $caller['file'] !== 'unknown') {
            $result .= " in {$caller['file']}:{$caller['line']}";
        }

        return $result;
    }


    protected function shouldLog(string $level): bool {
        return self::convertLogLevel($level) <= $this->logLevel;
    }

    public function log($level, string|Stringable $message, array $context = []): void {
        if (!$this->shouldLog($level)) {
            return;
        }

        // Deduplizierung: Prüfe ob gleicher Eintrag wie zuvor
        if ($this->deduplicationEnabled) {
            $currentKey = $this->createLogKey($level, $message, $context);

            if ($this->lastLogKey === $currentKey) {
                // Gleicher Eintrag - nur Zähler erhöhen, nicht loggen
                $this->duplicateCount++;
                return;
            }

            // Neuer, anderer Eintrag - vorherigen ausstehenden Eintrag ausgeben
            if ($this->lastLogKey !== null && $this->lastLevel !== null) {
                $this->writePendingEntry();
            }

            // Aktuellen Eintrag als ausstehend speichern
            $this->lastLogKey = $currentKey;
            $this->lastLevel = $level;
            $this->lastMessage = (string)$message;
            $this->lastContext = $context;
            $this->duplicateCount = 0;
            return;
        }

        // Ohne Deduplizierung: sofort ausgeben
        $logEntry = $this->generateLogEntry($level, $message, $context);
        $this->writeLog($logEntry, $level);
    }

    public function generateLogEntry($level, string|Stringable $message, array $context = []): string {
        $timestamp = date('Y-m-d H:i:s');
        // Bei DEBUG-Level (7) werden Datei und Zeile inkludiert
        $caller = self::getCallerFunction($this->logLevel === 7);
        $contextString = empty($context) ? "" : " " . json_encode($context);
        return "[{$timestamp}] {$level} [{$caller}]: {$message}{$contextString}";
    }

    abstract protected function writeLog(string $logEntry, string $level): void;

    public function emergency(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }
    public function alert(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::ALERT, $message, $context);
    }
    public function critical(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }
    public function error(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::ERROR, $message, $context);
    }
    public function warning(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::WARNING, $message, $context);
    }
    public function notice(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::NOTICE, $message, $context);
    }
    public function info(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::INFO, $message, $context);
    }
    public function debug(string|Stringable $message, array $context = []): void {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
}
