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

        // Standard-Logdatei, falls die gegebene Datei nicht beschreibbar ist
        if (is_null($logFile) || ($failSafe && (!is_dir(dirname($logFile)) || !is_writable(dirname($logFile))))) {
            $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'default.log';
        }

        $this->logFile = $logFile;
        $logDir = dirname($logFile);

        // Sicherstellen, dass das Verzeichnis existiert
        if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
            $this->fallbackToConsole("Logverzeichnis konnte nicht erstellt werden: $logDir");
            throw new FileNotWrittenException("Logverzeichnis konnte nicht erstellt werden: $logDir");
        }

        // Sicherstellen, dass die Datei existiert und UTF-8-BOM enthält
        if (!file_exists($logFile)) {
            if (@file_put_contents($logFile, "\xEF\xBB\xBF") === false) { // UTF-8 BOM setzen
                $this->handleWriteError("Fehler beim Erstellen der Logdatei");
            }
        }
    }

    protected function writeLog(string $logEntry, string $level): void {
        // Log-Eintrag in UTF-8 umwandeln
        $logEntry = mb_convert_encoding($logEntry . PHP_EOL, 'UTF-8', 'auto');

        if (!is_writable($this->logFile)) {
            $this->fallbackToConsole("Logdatei ist nicht beschreibbar: " . $this->logFile);
            throw new FileNotWrittenException("Logdatei ist nicht beschreibbar: " . $this->logFile);
        }

        // Stream-Kontext für UTF-8-Schreiben nutzen
        $context = stream_context_create([
            'http' => [
                'header' => "Content-Type: text/plain; charset=UTF-8"
            ]
        ]);

        if (@file_put_contents($this->logFile, $logEntry, FILE_APPEND, $context) === false) {
            clearstatcache(true, $this->logFile); // Cache leeren für zweite Chance
            if (@file_put_contents($this->logFile, $logEntry, FILE_APPEND, $context) === false) {
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
