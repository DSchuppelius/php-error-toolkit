<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileLogger.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace ERRORToolkit\Logger;

use ERRORToolkit\Contracts\Abstracts\LoggerAbstract;
use ERRORToolkit\Exceptions\FileSystem\FileNotWrittenException;
use ERRORToolkit\Factories\ConsoleLoggerFactory;
use Psr\Log\LogLevel;

class FileLogger extends LoggerAbstract {
    protected string $logFile;
    protected bool $isLoggingError = false;

    public function __construct(?string $logFile = null, string $logLevel = LogLevel::DEBUG, bool $failSafe = true) {
        parent::__construct($logLevel);

        if (is_null($logFile) || ($failSafe && (!is_dir(dirname($logFile)) || !is_writable(dirname($logFile))))) {
            $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'default.log';
        }

        $this->logFile = $logFile;
        $logDir = dirname($logFile);

        if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
            $this->fallbackToConsole("Logverzeichnis konnte nicht erstellt werden: $logDir");
            throw new FileNotWrittenException("Logverzeichnis konnte nicht erstellt werden: $logDir");
        }

        if (!file_exists($logFile)) {
            if (@file_put_contents($logFile, "") === false) {
                $this->handleWriteError("Fehler beim Erstellen der Logdatei");
            }
        }
    }

    protected function writeLog(string $logEntry, string $level): void {
        if (!is_writable($this->logFile)) {
            $this->fallbackToConsole("Logdatei ist nicht beschreibbar: " . $this->logFile);
            throw new FileNotWrittenException("Logdatei ist nicht beschreibbar: " . $this->logFile);
        }

        if (@file_put_contents($this->logFile, $logEntry . PHP_EOL, FILE_APPEND) === false) {
            clearstatcache(true, $this->logFile); // Cache löschen für zweite Chance
            if (@file_put_contents($this->logFile, $logEntry . PHP_EOL, FILE_APPEND) === false) {
                $this->handleWriteError("Fehler beim Schreiben in die Logdatei");
            }
        }
    }

    private function handleWriteError(string $errorMessage): void {
        $error = error_get_last();
        $message = $error['message'] ?? 'Unbekannter Fehler';
        $finalMessage = "$errorMessage: $message";

        if (!$this->isLoggingError) {
            $this->isLoggingError = true;
            $this->fallbackToConsole($finalMessage);
            $this->isLoggingError = false;
        }

        throw new FileNotWrittenException($finalMessage);
    }

    private function fallbackToConsole(string $message): void {
        $consoleLogger = ConsoleLoggerFactory::getLogger();
        $consoleLogger->error($message);
    }

    public function getLogFile(): string {
        return $this->logFile;
    }
}