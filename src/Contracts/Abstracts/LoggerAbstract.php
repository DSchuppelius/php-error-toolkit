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

use Psr\Log\{InvalidArgumentException, LogLevel, LoggerInterface};
use Stringable;

abstract class LoggerAbstract implements LoggerInterface {
    public const CONTEXT_KEY_MESSAGE_HEX = '_hexMessage';

    protected int $logLevel;

    /**
     * Caller-Detection (debug_backtrace je Eintrag) — abschaltbar für heiße Pfade.
     */
    protected bool $callerDetectionEnabled = true;

    /**
     * Deduplizierung: Verhindert doppelte aufeinanderfolgende Log-Einträge.
     */
    protected bool $deduplicationEnabled = true;
    protected ?string $lastLogKey = null;
    protected int $duplicateCount = 0;
    protected ?string $lastLevel = null;
    protected ?string $lastMessage = null;
    protected array $lastContext = [];
    protected ?string $lastCaller = null;

    public function __construct(string $logLevel = LogLevel::DEBUG, bool $enableDeduplication = true) {
        $this->setLogLevel($logLevel);
        $this->deduplicationEnabled = $enableDeduplication;
    }

    /**
     * Destructor: Gibt ausstehende Log-Einträge aus.
     */
    public function __destruct() {
        $this->flushDuplicates();
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
     * Aktiviert oder deaktiviert die Caller-Detection (Backtrace je Eintrag).
     * Für heiße Log-Pfade abschaltbar; der Eintrag zeigt dann "-" als Quelle.
     */
    public function setCallerDetection(bool $enabled): void {
        $this->callerDetectionEnabled = $enabled;
    }

    public function isCallerDetectionEnabled(): bool {
        return $this->callerDetectionEnabled;
    }

    /**
     * Gibt ausstehende Log-Einträge aus.
     * Sollte am Ende einer Log-Session aufgerufen werden.
     */
    public function flushDuplicates(): void {
        if ($this->duplicateCount > 0) {
            $this->writeDuplicateSummary();
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
        $this->lastCaller = null;
    }

    /**
     * Schreibt die Wiederholungs-Zusammenfassung für den letzten Eintrag.
     *
     * Das erste Auftreten wurde bereits geschrieben (Write-Through);
     * "(xN)" zählt daher nur die unterdrückten Wiederholungen.
     */
    protected function writeDuplicateSummary(): void {
        if ($this->lastLevel === null || $this->lastMessage === null) {
            return;
        }

        $message = $this->lastMessage . " (x{$this->duplicateCount})";
        $logEntry = $this->generateLogEntry($this->lastLevel, $message, $this->lastContext, $this->lastCaller);
        $this->writeLog($logEntry, $this->lastLevel);
    }

    /**
     * Erzeugt einen eindeutigen Schlüssel für einen Log-Eintrag zur Deduplizierung.
     */
    protected function createLogKey(string $level, string|Stringable $message, array $context): string {
        $contextKey = '';
        if (!empty($context)) {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE);
            $contextKey = $encoded !== false ? $encoded : 'unserializable:' . json_last_error_msg();
        }
        return $level . '|' . (string) $message . '|' . $contextKey;
    }

    public function setLogLevel(string $logLevel): void {
        $this->logLevel = self::convertLogLevel($logLevel);
    }

    private static function convertLogLevel(string $logLevel): int {
        static $levels = [
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT => 1,
            LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3,
            LogLevel::WARNING => 4,
            LogLevel::NOTICE => 5,
            LogLevel::INFO => 6,
            LogLevel::DEBUG => 7,
        ];

        return $levels[$logLevel] ?? throw new InvalidArgumentException("Ungültiges LogLevel: {$logLevel}");
    }

    /**
     * Liste der internen Trait/Logger-Methoden, die im Backtrace übersprungen werden sollen.
     * Diese Liste wird sowohl von LoggerAbstract als auch vom ErrorLog Trait verwendet.
     */
    protected const INTERNAL_METHODS = [
        // ErrorLog Trait Methoden
        'logInternal',
        'logFallback',
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
        'resolveCaller',
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
            $function = $frame['function'];
            $class = $frame['class'] ?? null;

            // Überspringe interne Methoden
            if (in_array($function, self::INTERNAL_METHODS, true)) {
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

    /**
     * Ermittelt den Caller-String für einen Log-Eintrag
     * ('-' bei deaktivierter Caller-Detection).
     */
    protected function resolveCaller(): string {
        return $this->callerDetectionEnabled ? self::getCallerFunction($this->logLevel === 7) : '-';
    }

    public function log($level, string|Stringable $message, array $context = []): void {
        if (!is_string($level)) {
            throw new InvalidArgumentException('Log level must be one of the PSR-3 LogLevel strings.');
        }

        if (!$this->shouldLog($level)) {
            return;
        }

        // Deduplizierung (Write-Through): Das erste Auftreten wird sofort
        // geschrieben, nur unmittelbare Wiederholungen werden unterdrückt und
        // beim nächsten anderen Eintrag bzw. Flush als "(xN)" zusammengefasst.
        // So entsteht keine Ausgabe-Latenz (tail -f, Queue-Worker).
        $caller = null;

        if ($this->deduplicationEnabled) {
            $currentKey = $this->createLogKey($level, $message, $context);

            if ($this->lastLogKey === $currentKey) {
                // Wiederholung - nur Zähler erhöhen, nicht loggen
                $this->duplicateCount++;
                return;
            }

            // Anderer Eintrag: ggf. Wiederholungs-Zusammenfassung ausgeben
            $this->flushDuplicates();

            // Aktuellen Eintrag für die Duplikat-Erkennung merken; den Caller
            // jetzt festhalten, damit die (xN)-Summary die Quelle des ersten
            // Auftretens zeigt und nicht den Auslöser des späteren Flush
            $caller = $this->resolveCaller();
            $this->lastLogKey = $currentKey;
            $this->lastLevel = $level;
            $this->lastMessage = (string) $message;
            $this->lastContext = $context;
            $this->lastCaller = $caller;
            $this->duplicateCount = 0;
        }

        $logEntry = $this->generateLogEntry($level, $message, $context, $caller);
        $this->writeLog($logEntry, $level);
    }

    protected function generateLogEntry(string $level, string|Stringable $message, array $context = [], ?string $caller = null): string {
        [$context, $includeMessageHex] = $this->extractInternalContextFlags($context);

        $timestamp = date('Y-m-d H:i:s');
        $caller ??= $this->resolveCaller();
        $messageString = self::interpolate((string) $message, $context);
        $contextString = empty($context) ? '' : ' ' . json_encode($context);

        if (!$includeMessageHex) {
            return "[{$timestamp}] {$level} [{$caller}]: {$messageString}{$contextString}";
        }

        $prefix = "[{$timestamp}] {$level} [{$caller}]";
        $messageLine = "{$prefix}: {$messageString}{$contextString}";
        [$charLine, $alignedHexLine] = self::toAlignedHexLines($messageString);

        return $messageLine . "\n"
            . "{$prefix} [str]: {$charLine}" . "\n"
            . "{$prefix} [hex]: {$alignedHexLine}";
    }

    /**
     * Trennt interne Context-Flags vom auszugebenden Kontext.
     *
     * @return array{0: array, 1: bool}
     */
    protected function extractInternalContextFlags(array $context): array {
        $includeMessageHex = (bool) ($context[self::CONTEXT_KEY_MESSAGE_HEX] ?? false);
        unset($context[self::CONTEXT_KEY_MESSAGE_HEX]);

        return [$context, $includeMessageHex];
    }

    /**
     * Interpoliert PSR-3 Platzhalter ({key}) in der Nachricht mit Kontext-Werten.
     * Kontext-Schlüssel mit "_"-Präfix (interne Flags) werden ignoriert;
     * ohne Platzhalter ist der Aufruf ein No-op.
     */
    public static function interpolate(string $message, array $context): string {
        if ($context === [] || !str_contains($message, '{')) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($key) && !str_starts_with($key, '_')) {
                if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                    $replace['{' . $key . '}'] = (string) $val;
                } elseif (is_array($val)) {
                    $replace['{' . $key . '}'] = (string) json_encode($val);
                } elseif (is_object($val)) {
                    $replace['{' . $key . '}'] = get_class($val);
                }
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Konvertiert einen String in eine lesbare Byte-Hexdarstellung.
     */
    protected static function toHexString(string $value): string {
        if ($value == '') {
            return '';
        }

        $hex = strtoupper(bin2hex($value));
        return trim(chunk_split($hex, 2, ' '));
    }

    /**
     * Erzeugt zwei ausgerichtete Zeilen:
     * - Zeile 1: Zeichen mit Leerzeichen aufgefüllt (colWidth = hexWidth)
     * - Zeile 2: Hex-Bytes, spaltengenau darunter
     *
     * Jedes Unicode-Zeichen bekommt eine Spalte so breit wie seine Hex-Darstellung,
     * damit Zeichen und Code exakt übereinander stehen.
     *
     * @return array{0: string, 1: string}
     */
    protected static function toAlignedHexLines(string $value): array {
        if ($value === '') {
            return ['', ''];
        }

        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return [$value, ''];
        }

        $charCells = [];
        $hexCells = [];

        foreach ($chars as $char) {
            $hexBytes = strtoupper(bin2hex($char));
            $hexFormatted = trim(chunk_split($hexBytes, 2, ' ')); // z.B. "41" oder "C3 A4"

            $hexWidth = strlen($hexFormatted);           // immer ASCII
            $charDisplayWidth = mb_strlen($char, 'UTF-8');       // visuelle Breite (1 für normale Zeichen)
            $colWidth = max($charDisplayWidth, $hexWidth);

            // Zeichen visuell auffüllen (multi-byte-sicher: Leerzeichen manuell anhängen)
            $charCells[] = $char . str_repeat(' ', $colWidth - $charDisplayWidth);
            // Hex auffüllen (alles ASCII, str_pad reicht)
            $hexCells[] = str_pad($hexFormatted, $colWidth);
        }

        return [
            implode(' ', $charCells),
            implode(' ', $hexCells),
        ];
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
