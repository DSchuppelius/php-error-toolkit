<?php
/*
 * Created on   : Fri Oct 25 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ErrorLog.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Traits;

use BadMethodCallException;
use Closure;
use ERRORToolkit\LoggerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Throwable;

/**
 * ErrorLog Trait - Provides convenient logging methods via magic methods.
 * 
 * Logging methods (return void):
 * @method void logDebug(string $message, array $context = [])
 * @method void logInfo(string $message, array $context = [])
 * @method void logNotice(string $message, array $context = [])
 * @method void logWarning(string $message, array $context = [])
 * @method void logError(string $message, array $context = [])
 * @method void logCritical(string $message, array $context = [])
 * @method void logAlert(string $message, array $context = [])
 * @method void logEmergency(string $message, array $context = [])
 * 
 * Static logging methods (return void):
 * @method static void logDebug(string $message, array $context = [])
 * @method static void logInfo(string $message, array $context = [])
 * @method static void logNotice(string $message, array $context = [])
 * @method static void logWarning(string $message, array $context = [])
 * @method static void logError(string $message, array $context = [])
 * @method static void logCritical(string $message, array $context = [])
 * @method static void logAlert(string $message, array $context = [])
 * @method static void logEmergency(string $message, array $context = [])
 */
trait ErrorLog {
    protected static ?LoggerInterface $logger = null;

    /**
     * PSR-LogLevel für magische Methoden
     */
    private static array $logLevelMap = [
        'Debug'     => LogLevel::DEBUG,
        'Info'      => LogLevel::INFO,
        'Notice'    => LogLevel::NOTICE,
        'Warning'   => LogLevel::WARNING,
        'Error'     => LogLevel::ERROR,
        'Critical'  => LogLevel::CRITICAL,
        'Alert'     => LogLevel::ALERT,
        'Emergency' => LogLevel::EMERGENCY,
    ];

    private function initializeLogger(?LoggerInterface $logger): void {
        if (!is_null($logger)) {
            $this->setLogger($logger);
        }
    }

    /**
     * Ermittelt den Projektnamen anhand des Namespaces der aufrufenden Klasse.
     */
    private static function detectProjectName(): string {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        foreach ($backtrace as $trace) {
            if (isset($trace['class']) && !str_starts_with($trace['class'], 'ERRORToolkit')) {
                $namespaceParts = explode('\\', $trace['class']);
                return $namespaceParts[0]; // Erster Teil des Namespaces als Projektnamen
            }
        }

        return 'UnknownProject'; // Fallback, falls nichts gefunden wird
    }

    /**
     * Gibt den aktuell gesetzten Logger zurück (oder null)
     */
    public static function getLogger(): ?LoggerInterface {
        return self::$logger ?? LoggerRegistry::getLogger();
    }

    /**
     * Prüft, ob ein Logger gesetzt ist
     */
    public static function hasLogger(): bool {
        return self::$logger !== null || LoggerRegistry::hasLogger();
    }

    /**
     * Setzt einen PSR-3 kompatiblen Logger (global für statische Nutzung)
     */
    public static function setLogger(?LoggerInterface $logger = null): void {
        if (is_null($logger)) {
            self::$logger = LoggerRegistry::getLogger();
        } else {
            self::$logger = $logger;
            LoggerRegistry::setLogger($logger);
        }
    }

    /**
     * Allgemeine Logging-Funktion für Instanz- und statische Nutzung
     */
    private static function logInternal(string $level, string $message, array $context = []): void {
        if (is_null(self::$logger)) {
            self::$logger = LoggerRegistry::getLogger();
        }

        if (self::$logger) {
            self::$logger->log($level, $message, $context);
        } else {
            // Fallback-Logging
            openlog(self::detectProjectName(), LOG_PID | LOG_PERROR, defined('LOG_LOCAL0') ? LOG_LOCAL0 : LOG_USER);

            $logString = sprintf("[%s] [%s] %s", date('Y-m-d H:i:s'), ucfirst($level), $message);

            if (ini_get('error_log')) {
                error_log($logString);
            } elseif (function_exists('syslog')) {
                syslog(self::getSyslogLevel($level), $logString);
            } else {
                file_put_contents(sys_get_temp_dir() . "/php-error-toolkit.log", $logString . PHP_EOL, FILE_APPEND);
            }
            closelog();
        }
    }

    /**
     * Wandelt PSR-LogLevel in syslog-Level um
     */
    private static function getSyslogLevel(string $level): int {
        return match ($level) {
            LogLevel::EMERGENCY => LOG_EMERG,
            LogLevel::ALERT     => LOG_ALERT,
            LogLevel::CRITICAL  => LOG_CRIT,
            LogLevel::ERROR     => LOG_ERR,
            LogLevel::WARNING   => LOG_WARNING,
            LogLevel::NOTICE    => LOG_NOTICE,
            LogLevel::INFO      => LOG_INFO,
            LogLevel::DEBUG     => LOG_DEBUG,
            default             => LOG_INFO,
        };
    }

    // ========================================================================
    // Erweiterte Logging-Funktionen
    // ========================================================================

    /**
     * Loggt eine Throwable/Exception mit vollständigem Stack-Trace
     * 
     * @param Throwable $exception Die zu loggende Exception
     * @param string $level Das Log-Level (Standard: ERROR)
     * @param array $context Zusätzlicher Kontext
     */
    public static function logException(Throwable $exception, string $level = LogLevel::ERROR, array $context = []): void {
        $context = array_merge($context, self::extractExceptionContext($exception));

        $message = sprintf(
            "%s: %s in %s:%d",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        self::logInternal($level, $message, $context);
    }

    /**
     * Extrahiert Kontext-Informationen aus einer Exception
     */
    private static function extractExceptionContext(Throwable $exception): array {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($exception->getPrevious() !== null) {
            $context['previous'] = self::extractExceptionContext($exception->getPrevious());
        }

        return $context;
    }

    /**
     * Loggt bedingt - nur wenn die Bedingung wahr ist
     * 
     * @param bool $condition Die zu prüfende Bedingung
     * @param string $level Das Log-Level
     * @param string $message Die Nachricht
     * @param array $context Der Kontext
     */
    public static function logIf(bool $condition, string $level, string $message, array $context = []): void {
        if ($condition) {
            self::logInternal($level, $message, $context);
        }
    }

    /**
     * Loggt bedingt - nur wenn die Bedingung falsch ist
     * 
     * @param bool $condition Die zu prüfende Bedingung
     * @param string $level Das Log-Level
     * @param string $message Die Nachricht
     * @param array $context Der Kontext
     */
    public static function logUnless(bool $condition, string $level, string $message, array $context = []): void {
        if (!$condition) {
            self::logInternal($level, $message, $context);
        }
    }

    /**
     * Führt eine Closure aus und loggt die Ausführungszeit
     * 
     * @template T
     * @param Closure(): T $callback Die auszuführende Funktion
     * @param string $description Beschreibung der Operation
     * @param string $level Das Log-Level (Standard: DEBUG)
     * @return T Das Ergebnis der Closure
     */
    public static function logWithTimer(Closure $callback, string $description, string $level = LogLevel::DEBUG): mixed {
        $startTime = hrtime(true);

        try {
            $result = $callback();
            $duration = (hrtime(true) - $startTime) / 1_000_000; // Nanosekunden zu Millisekunden

            self::logInternal($level, sprintf(
                "%s completed in %.2f ms",
                $description,
                $duration
            ));

            return $result;
        } catch (Throwable $e) {
            $duration = (hrtime(true) - $startTime) / 1_000_000;

            self::logInternal(LogLevel::ERROR, sprintf(
                "%s failed after %.2f ms: %s",
                $description,
                $duration,
                $e->getMessage()
            ), self::extractExceptionContext($e));

            throw $e;
        }
    }

    /**
     * Loggt eine Nachricht und gibt eine Variable zurück (für Fluent-Chains)
     * 
     * @template T
     * @param T $value Der Wert der zurückgegeben werden soll
     * @param string $level Das Log-Level
     * @param string $message Die Nachricht
     * @param array $context Der Kontext
     * @return T Der übergebene Wert
     */
    public static function logAndReturn(mixed $value, string $level, string $message, array $context = []): mixed {
        self::logInternal($level, $message, $context);
        return $value;
    }

    /**
     * Erstellt einen Kontext mit automatisch erfassten Debug-Informationen
     * 
     * @param array $additionalContext Zusätzlicher Kontext
     * @return array Der erweiterte Kontext
     */
    public static function createDebugContext(array $additionalContext = []): array {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? [];

        return array_merge([
            '_debug' => [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'timestamp' => microtime(true),
                'file' => $caller['file'] ?? 'unknown',
                'line' => $caller['line'] ?? 0,
                'function' => $caller['function'] ?? 'unknown',
                'class' => $caller['class'] ?? null,
            ],
        ], $additionalContext);
    }

    /**
     * Interpoliert PSR-3 Platzhalter in der Nachricht mit Kontext-Werten
     * 
     * @param string $message Die Nachricht mit {placeholder} Platzhaltern
     * @param array $context Der Kontext mit Ersetzungswerten
     * @return string Die interpolierte Nachricht
     */
    public static function interpolateMessage(string $message, array $context): string {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($key) && !str_starts_with($key, '_')) {
                if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                    $replace['{' . $key . '}'] = (string) $val;
                } elseif (is_array($val)) {
                    $replace['{' . $key . '}'] = json_encode($val);
                } elseif (is_object($val)) {
                    $replace['{' . $key . '}'] = get_class($val);
                }
            }
        }
        return strtr($message, $replace);
    }

    /**
     * Parst den Methodennamen und gibt das Log-Level zurück.
     * Unterstützt: log{Level}
     * 
     * @return string|null Das PSR-3 Log-Level oder null
     */
    private static function parseMethodName(string $name): ?string {
        if (!str_starts_with($name, 'log')) {
            return null;
        }

        $suffix = substr($name, 3); // Entferne 'log' Präfix

        return self::$logLevelMap[$suffix] ?? null;
    }

    /**
     * Magische Methode für Instanzmethoden (nicht-statisch)
     * 
     * Unterstützt: log{Level}(string $message, array $context = [])
     */
    public function __call(string $name, array $arguments): mixed {
        return self::handleMagicCall($name, $arguments);
    }

    /**
     * Magische Methode für statische Methoden (statisch)
     * 
     * Unterstützt: log{Level}(string $message, array $context = [])
     */
    public static function __callStatic(string $name, array $arguments): mixed {
        return self::handleMagicCall($name, $arguments);
    }

    /**
     * Gemeinsame Implementierung für __call und __callStatic
     */
    private static function handleMagicCall(string $name, array $arguments): mixed {
        $level = self::parseMethodName($name);

        if ($level !== null) {
            array_unshift($arguments, $level);
            self::logInternal(...$arguments);
            return null;
        }

        throw new BadMethodCallException("Methode $name existiert nicht in " . __TRAIT__);
    }

    // ========================================================================
    // Log-and-Throw Methoden - Echte statische Methoden für IDE-Unterstützung
    // ========================================================================

    /**
     * Gemeinsame Implementierung für alle logAndThrow-Methoden
     * 
     * @template T of Throwable
     * @param string $level PSR-3 Log-Level
     * @param class-string<T> $exceptionClass
     * @throws T
     */
    private static function doLogAndThrow(string $level, string $exceptionClass, string $message, array $context = [], ?Throwable $previous = null, int $code = 0): never {
        self::logInternal($level, $message, $context);
        throw new $exceptionClass($message, $code, $previous);
    }

    /**
     * Loggt eine Error-Nachricht und wirft eine Exception.
     * 
     * @template T of Throwable
     * @param class-string<T> $exceptionClass
     * @throws T
     */
    public static function logErrorAndThrow(string $exceptionClass, string $message, array $context = [], ?Throwable $previous = null, int $code = 0): never {
        self::doLogAndThrow(LogLevel::ERROR, $exceptionClass, $message, $context, $previous, $code);
    }

    /**
     * Loggt eine Critical-Nachricht und wirft eine Exception.
     * 
     * @template T of Throwable
     * @param class-string<T> $exceptionClass
     * @throws T
     */
    public static function logCriticalAndThrow(string $exceptionClass, string $message, array $context = [], ?Throwable $previous = null, int $code = 0): never {
        self::doLogAndThrow(LogLevel::CRITICAL, $exceptionClass, $message, $context, $previous, $code);
    }

    /**
     * Loggt eine Alert-Nachricht und wirft eine Exception.
     * 
     * @template T of Throwable
     * @param class-string<T> $exceptionClass
     * @throws T
     */
    public static function logAlertAndThrow(string $exceptionClass, string $message, array $context = [], ?Throwable $previous = null, int $code = 0): never {
        self::doLogAndThrow(LogLevel::ALERT, $exceptionClass, $message, $context, $previous, $code);
    }

    /**
     * Loggt eine Emergency-Nachricht und wirft eine Exception.
     * 
     * @template T of Throwable
     * @param class-string<T> $exceptionClass
     * @throws T
     */
    public static function logEmergencyAndThrow(string $exceptionClass, string $message, array $context = [], ?Throwable $previous = null, int $code = 0): never {
        self::doLogAndThrow(LogLevel::EMERGENCY, $exceptionClass, $message, $context, $previous, $code);
    }
}
