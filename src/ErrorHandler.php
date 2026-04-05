<?php
/*
 * Created on   : Fri Apr 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ErrorHandler.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit;

use ErrorException;
use Psr\Log\{LoggerInterface, LogLevel};
use Throwable;

/**
 * Globaler Error-Handler für PHP-Fehler, nicht-gefangene Exceptions und fatale Fehler.
 *
 * Registriert:
 * - set_error_handler()          → Fängt Warnings, Notices, Deprecations ab
 * - set_exception_handler()      → Fängt nicht-gefangene Exceptions ab
 * - register_shutdown_function() → Fängt fatale Fehler ab (E_ERROR, E_PARSE, E_CORE_ERROR, etc.)
 *
 * Nutzung:
 *   $handler = ErrorHandler::register($logger);
 *   // ... Anwendung läuft ...
 *   $handler->unregister(); // Optional: Handler wieder entfernen
 */
class ErrorHandler {
    private ?LoggerInterface $logger;

    /** Ob der Handler registriert ist */
    private bool $registered = false;

    /** Ob Warnings/Notices als ErrorException geworfen werden sollen */
    private bool $throwOnWarning;

    /** Ob handleException() den Prozess beenden soll (Standard: true) */
    private bool $exitOnException;

    /** Reservierter Memory-Buffer für OOM-Situationen (wird im Shutdown freigegeben) */
    private ?string $memoryReserve = null;

    /** Standard-Größe des Memory-Buffers in Bytes (10 KB) */
    private const MEMORY_RESERVE_SIZE = 10240;

    /** Registrierte Callbacks pro LogLevel */
    private array $listeners = [];

    /** Singleton-Guard: Aktive registrierte Instanz */
    private static ?self $activeInstance = null;

    /** Error-Severity → PSR-3 LogLevel Mapping */
    private const SEVERITY_MAP = [
        E_ERROR             => LogLevel::CRITICAL,
        E_WARNING           => LogLevel::WARNING,
        E_PARSE             => LogLevel::CRITICAL,
        E_NOTICE            => LogLevel::NOTICE,
        E_CORE_ERROR        => LogLevel::CRITICAL,
        E_CORE_WARNING      => LogLevel::WARNING,
        E_COMPILE_ERROR     => LogLevel::CRITICAL,
        E_COMPILE_WARNING   => LogLevel::WARNING,
        E_USER_ERROR        => LogLevel::ERROR,
        E_USER_WARNING      => LogLevel::WARNING,
        E_USER_NOTICE       => LogLevel::NOTICE,
        E_RECOVERABLE_ERROR => LogLevel::ERROR,
        E_DEPRECATED        => LogLevel::NOTICE,
        E_USER_DEPRECATED   => LogLevel::NOTICE,
    ];

    /** Error-Severity → Menschenlesbarer Name */
    private const SEVERITY_NAMES = [
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    ];

    /** Fatale Error-Typen die im Shutdown-Handler erkannt werden */
    private const FATAL_ERRORS = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
    ];

    private function __construct(?LoggerInterface $logger, bool $throwOnWarning = false, bool $exitOnException = true) {
        $this->logger = $logger;
        $this->throwOnWarning = $throwOnWarning;
        $this->exitOnException = $exitOnException;
    }

    /**
     * Registriert den globalen Error-Handler.
     *
     * @param LoggerInterface|null $logger Logger-Instanz (null = aus LoggerRegistry)
     * @param bool $throwOnWarning Ob Warnings als ErrorException geworfen werden sollen
     * @param bool $exitOnException Ob handleException() den Prozess beendet (Standard: true)
     * @throws \LogicException wenn bereits ein ErrorHandler registriert ist
     */
    public static function register(?LoggerInterface $logger = null, bool $throwOnWarning = false, bool $exitOnException = true): self {
        if (self::$activeInstance !== null && self::$activeInstance->registered) {
            throw new \LogicException('ErrorHandler ist bereits registriert. Erst unregister() aufrufen.');
        }

        $handler = new self($logger, $throwOnWarning, $exitOnException);
        $handler->doRegister();
        return $handler;
    }

    /**
     * Erstellt eine Handler-Instanz ohne globale Registrierung.
     * Nützlich für Frameworks, die ihre eigenen Handler-Mechanismen haben
     * und handleError()/handleException() manuell aufrufen möchten.
     */
    public static function createUnregistered(?LoggerInterface $logger = null, bool $throwOnWarning = false, bool $exitOnException = false): self {
        return new self($logger, $throwOnWarning, $exitOnException);
    }

    /**
     * Entfernt den Error-Handler und stellt die vorherigen Handler wieder her.
     */
    public function unregister(): void {
        if (!$this->registered) {
            return;
        }

        restore_error_handler();
        restore_exception_handler();

        $this->memoryReserve = null;
        $this->listeners = [];
        $this->registered = false;
        self::$activeInstance = null;
    }

    /**
     * Prüft ob der Handler registriert ist.
     */
    public function isRegistered(): bool {
        return $this->registered;
    }

    /**
     * Gibt die aktuell registrierte Instanz zurück (oder null).
     */
    public static function getActiveInstance(): ?self {
        return self::$activeInstance;
    }

    /**
     * Registriert einen Callback der bei bestimmten Log-Levels aufgerufen wird.
     *
     * @param string $level PSR-3 LogLevel (z.B. LogLevel::CRITICAL)
     * @param callable(string $message, array $context): void $callback
     */
    public function addListener(string $level, callable $callback): self {
        $this->listeners[$level][] = $callback;
        return $this;
    }

    /**
     * Error-Handler Callback für set_error_handler().
     * Fängt Warnings, Notices, Deprecations etc. ab.
     *
     * @return bool true wenn der Fehler behandelt wurde
     * @throws ErrorException wenn $throwOnWarning aktiv und Severity >= E_WARNING
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool {
        // Respektiere error_reporting() und @ Operator
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $level = self::SEVERITY_MAP[$severity] ?? LogLevel::ERROR;
        $severityName = self::SEVERITY_NAMES[$severity] ?? 'UNKNOWN';

        $this->log($level, sprintf('[%s] %s', $severityName, $message), [
            'severity' => $severity,
            'severity_name' => $severityName,
            'file' => $file,
            'line' => $line,
        ]);

        // Bei throwOnWarning: Fehler als Exception werfen (für strengen Modus)
        if ($this->throwOnWarning && $this->isSevere($severity)) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }

        // Bei User-Errors immer werfen (E_USER_ERROR soll den Ablauf stoppen)
        if ($severity === E_USER_ERROR || $severity === E_RECOVERABLE_ERROR) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }

        // Fehler als behandelt markieren (PHP-eigene Fehlerbehandlung wird nicht aufgerufen)
        return true;
    }

    /**
     * Exception-Handler Callback für set_exception_handler().
     * Fängt nicht-gefangene Exceptions ab.
     * Beendet den Prozess mit Exit-Code 255 (wie PHP-Standard), wenn $exitOnException aktiv.
     */
    public function handleException(Throwable $exception): void {
        $this->log(LogLevel::CRITICAL, sprintf(
            'Nicht-gefangene Exception: %s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ), [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        if ($this->exitOnException) {
            // @codeCoverageIgnoreStart
            exit(255);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Shutdown-Handler Callback für register_shutdown_function().
     * Fängt fatale Fehler ab (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR).
     */
    public function handleShutdown(): void {
        // Memory-Reserve freigeben damit im OOM-Fall noch geloggt werden kann
        $this->memoryReserve = null;

        $error = error_get_last();

        if ($error === null) {
            return;
        }

        if (!in_array($error['type'], self::FATAL_ERRORS, true)) {
            return;
        }

        $severityName = self::SEVERITY_NAMES[$error['type']] ?? 'FATAL';

        $this->log(LogLevel::EMERGENCY, sprintf(
            'Fataler Fehler [%s]: %s in %s:%d',
            $severityName,
            $error['message'],
            $error['file'],
            $error['line']
        ), [
            'severity' => $error['type'],
            'severity_name' => $severityName,
            'file' => $error['file'],
            'line' => $error['line'],
        ]);

        // Logger flushen (Deduplizierung auflösen)
        $logger = $this->getLogger();
        if ($logger instanceof \ERRORToolkit\Contracts\Abstracts\LoggerAbstract) {
            $logger->flushDuplicates();
        }
    }

    /**
     * Registriert alle Handler.
     */
    private function doRegister(): void {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);

        // Memory-Reserve für OOM-Situationen allokieren
        $this->memoryReserve = str_repeat("\0", self::MEMORY_RESERVE_SIZE);

        $this->registered = true;
        self::$activeInstance = $this;
    }

    /**
     * Loggt eine Nachricht über den konfigurierten Logger.
     */
    private function log(string $level, string $message, array $context = []): void {
        $logger = $this->getLogger();

        if ($logger !== null) {
            $logger->log($level, $message, $context);
        } else {
            // Absoluter Fallback: error_log()
            error_log(sprintf('[%s] %s', strtoupper($level), $message));
        }

        // Registrierte Listener für dieses Level benachrichtigen
        $this->notifyListeners($level, $message, $context);
    }

    /**
     * Benachrichtigt registrierte Listener für ein bestimmtes Log-Level.
     */
    private function notifyListeners(string $level, string $message, array $context): void {
        if (!isset($this->listeners[$level])) {
            return;
        }

        foreach ($this->listeners[$level] as $callback) {
            try {
                $callback($message, $context);
            } catch (Throwable) {
                // Listener-Fehler dürfen den Error-Handler nicht crashen
            }
        }
    }

    /**
     * Gibt den aktiven Logger zurück.
     */
    private function getLogger(): ?LoggerInterface {
        return $this->logger ?? LoggerRegistry::getLogger();
    }

    /**
     * Prüft ob die Severity "schwer" genug ist um geworfen zu werden.
     */
    private function isSevere(int $severity): bool {
        return in_array($severity, [
            E_ERROR,
            E_WARNING,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING,
            E_USER_ERROR,
            E_USER_WARNING,
            E_RECOVERABLE_ERROR,
        ], true);
    }
}
