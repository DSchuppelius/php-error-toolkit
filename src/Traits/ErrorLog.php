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
use ERRORToolkit\LoggerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
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
 * 
 * Log-and-throw methods (return never, always throw):
 * @method never logDebugAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method never logInfoAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method never logNoticeAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method never logWarningAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method never logErrorAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method never logCriticalAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method never logAlertAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method never logEmergencyAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * 
 * Static log-and-throw methods (return never, always throw):
 * @method static never logDebugAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method static never logInfoAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method static never logNoticeAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method static never logWarningAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method static never logErrorAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method static never logCriticalAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method static never logAlertAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
 * @method static never logEmergencyAndThrow(class-string<Throwable> $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
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

    /**
     * Parst den Methodennamen und gibt das Log-Level sowie den Suffix zurück.
     * Unterstützt: log{Level} und log{Level}AndThrow
     * 
     * @return array{level: string, andThrow: bool}|null
     */
    private static function parseMethodName(string $name): ?array {
        if (!str_starts_with($name, 'log')) {
            return null;
        }

        $suffix = substr($name, 3); // Entferne 'log' Präfix
        $andThrow = false;

        if (str_ends_with($suffix, 'AndThrow')) {
            $andThrow = true;
            $suffix = substr($suffix, 0, -8); // Entferne 'AndThrow' Suffix
        }

        if (isset(self::$logLevelMap[$suffix])) {
            return [
                'level' => self::$logLevelMap[$suffix],
                'andThrow' => $andThrow,
            ];
        }

        return null;
    }

    /**
     * Magische Methode für Instanzmethoden (nicht-statisch)
     * 
     * Unterstützt:
     * - log{Level}(string $message, array $context = [])
     * - log{Level}AndThrow(string $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
     */
    public function __call(string $name, array $arguments): mixed {
        $parsed = self::parseMethodName($name);

        if ($parsed !== null) {
            if ($parsed['andThrow']) {
                return $this->handleLogAndThrow($parsed['level'], $arguments);
            }

            array_unshift($arguments, $parsed['level']);
            self::logInternal(...$arguments);
            return null;
        }

        throw new BadMethodCallException("Methode $name existiert nicht in " . __TRAIT__);
    }

    /**
     * Magische Methode für statische Methoden (statisch)
     * 
     * Unterstützt:
     * - log{Level}(string $message, array $context = [])
     * - log{Level}AndThrow(string $exceptionClass, string $message, array $context = [], ?Throwable $previous = null)
     */
    public static function __callStatic(string $name, array $arguments): mixed {
        $parsed = self::parseMethodName($name);

        if ($parsed !== null) {
            if ($parsed['andThrow']) {
                return self::handleLogAndThrowStatic($parsed['level'], $arguments);
            }

            array_unshift($arguments, $parsed['level']);
            self::logInternal(...$arguments);
            return null;
        }

        throw new BadMethodCallException("Methode $name existiert nicht in " . __TRAIT__);
    }

    /**
     * Interne Hilfsmethode für logAndThrow via magische Methode (Instanz)
     * 
     * @param string $level PSR-LogLevel
     * @param array $arguments [exceptionClass, message, context?, previous?]
     * @return never
     */
    private function handleLogAndThrow(string $level, array $arguments): never {
        $exceptionClass = $arguments[0] ?? \RuntimeException::class;
        $message = $arguments[1] ?? '';
        $context = $arguments[2] ?? [];
        $previous = $arguments[3] ?? null;

        self::logInternal($level, $message, $context);
        throw new $exceptionClass($message, 0, $previous);
    }

    /**
     * Interne Hilfsmethode für logAndThrow via magische Methode (statisch)
     * 
     * @param string $level PSR-LogLevel
     * @param array $arguments [exceptionClass, message, context?, previous?]
     * @return never
     */
    private static function handleLogAndThrowStatic(string $level, array $arguments): never {
        $exceptionClass = $arguments[0] ?? \RuntimeException::class;
        $message = $arguments[1] ?? '';
        $context = $arguments[2] ?? [];
        $previous = $arguments[3] ?? null;

        self::logInternal($level, $message, $context);
        throw new $exceptionClass($message, 0, $previous);
    }
}