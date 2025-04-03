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

trait ErrorLog {
    protected static ?LoggerInterface $logger = null;

    /**
     * PSR-LogLevel für magische Methoden
     */
    private static array $logLevelMap = [
        'logDebug'     => LogLevel::DEBUG,
        'logInfo'      => LogLevel::INFO,
        'logNotice'    => LogLevel::NOTICE,
        'logWarning'   => LogLevel::WARNING,
        'logError'     => LogLevel::ERROR,
        'logCritical'  => LogLevel::CRITICAL,
        'logAlert'     => LogLevel::ALERT,
        'logEmergency' => LogLevel::EMERGENCY,
    ];

    private function initializeLogger(?LoggerInterface $logger): void {
        if (!is_null($logger)) {
            $this->setLogger($logger);
            return;
        }

        $projectName = $this->detectProjectName();

        if (function_exists('openlog')) {
            openlog($projectName, LOG_PID | LOG_PERROR, defined('LOG_LOCAL0') ? LOG_LOCAL0 : LOG_USER);
        }
    }

    /**
     * Ermittelt den Projektnamen anhand des Namespaces der aufrufenden Klasse.
     */
    private function detectProjectName(): string {
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
            $logString = sprintf("[%s] [%s] %s", date('Y-m-d H:i:s'), ucfirst($level), $message);

            if (ini_get('error_log')) {
                error_log($logString);
            } elseif (function_exists('syslog')) {
                syslog(self::getSyslogLevel($level), $logString);
            } else {
                file_put_contents(sys_get_temp_dir() . "/php-config-toolkit.log", $logString . PHP_EOL, FILE_APPEND);
            }
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
     * Magische Methode für Instanzmethoden (nicht-statisch)
     */
    public function __call(string $name, array $arguments): void {
        if (isset(self::$logLevelMap[$name])) {
            array_unshift($arguments, self::$logLevelMap[$name]);
            self::logInternal(...$arguments);
        } else {
            throw new BadMethodCallException("Methode $name existiert nicht in " . __TRAIT__);
        }
    }

    /**
     * Magische Methode für statische Methoden (statisch)
     */
    public static function __callStatic(string $name, array $arguments): void {
        if (isset(self::$logLevelMap[$name])) {
            array_unshift($arguments, self::$logLevelMap[$name]);
            self::logInternal(...$arguments);
        } else {
            throw new BadMethodCallException("Methode $name existiert nicht in " . __TRAIT__);
        }
    }
}
