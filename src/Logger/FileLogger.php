<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileLogger.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace ERRORToolkit\Logger;

use ERRORToolkit\Contracts\Abstracts\LoggerAbstract;
use ERRORToolkit\Exceptions\FileSystem\FileNotWrittenException;
use ERRORToolkit\Factories\ConsoleLoggerFactory;
use Psr\Log\LogLevel;

class FileLogger extends LoggerAbstract {
    protected string $logFile;
    protected bool $isLoggingError = false;
    protected int $maxFileSize; // in Bytes
    protected bool $rotateLogs; // ob ein Archiv erstellt werden soll
    protected int $filePermissions; // Berechtigungen für neue Logdateien

    public function __construct(?string $logFile = null, string $logLevel = LogLevel::DEBUG, bool $failSafe = true, int $maxFileSize = 5242880, bool $rotateLogs = true, bool $enableDeduplication = true, int $filePermissions = 0660) {
        parent::__construct($logLevel, $enableDeduplication);

        // Standard-Logdatei, falls die gegebene Datei nicht beschreibbar ist
        if (is_null($logFile) || ($failSafe && (!is_dir(dirname($logFile)) || !is_writable(dirname($logFile))))) {
            $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'default.log';
        }

        $this->logFile = $logFile;
        $this->maxFileSize = $maxFileSize;
        $this->rotateLogs = $rotateLogs;
        $this->filePermissions = $filePermissions;

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
            @chmod($logFile, $this->filePermissions);
        }
    }

    protected function writeLog(string $logEntry, string $level): void {
        // Log-Eintrag in UTF-8 umwandeln
        $logEntry = mb_convert_encoding($logEntry . PHP_EOL, 'UTF-8', 'auto');

        // Prüfen auf maximale Dateigröße (mit clearstatcache für aktuelle Werte)
        clearstatcache(true, $this->logFile);
        if (file_exists($this->logFile) && filesize($this->logFile) >= $this->maxFileSize) {
            $this->rotateLogFile();
        }

        // Datei mit exklusivem Lock schreiben (atomar, prozesssicher)
        if (@file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            clearstatcache(true, $this->logFile);
            // Datei existiert möglicherweise nicht mehr nach Rotation durch anderen Prozess
            if (!file_exists($this->logFile)) {
                @file_put_contents($this->logFile, "\xEF\xBB\xBF");
                @chmod($this->logFile, $this->filePermissions);
            }
            if (@file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
                $this->handleWriteError("Fehler beim Schreiben in die Logdatei");
            }
        }
    }


    public function getMaxFileSize(): int {
        return $this->maxFileSize;
    }

    public function setMaxFileSize(int $bytes): void {
        $this->maxFileSize = $bytes;
    }

    public function getFilePermissions(): int {
        return $this->filePermissions;
    }

    public function setFilePermissions(int $permissions): void {
        $this->filePermissions = $permissions;
    }

    private function rotateLogFile(): void {
        // Lock-Datei verwenden um Race Conditions bei paralleler Rotation zu verhindern
        $lockFile = $this->logFile . '.lock';
        $lockHandle = @fopen($lockFile, 'c');

        if ($lockHandle === false) {
            // Fallback: Rotation ohne Lock versuchen
            $this->doRotate();
            return;
        }

        try {
            // Nicht-blockierendes Lock: Wenn ein anderer Prozess bereits rotiert, überspringen
            if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
                // Ein anderer Prozess rotiert gerade - einfach weitermachen
                return;
            }

            // Erneut prüfen nach Lock-Erwerb (Double-Check-Pattern)
            clearstatcache(true, $this->logFile);
            if (!file_exists($this->logFile) || filesize($this->logFile) < $this->maxFileSize) {
                // Ein anderer Prozess hat bereits rotiert
                return;
            }

            $this->doRotate();
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockFile);
        }
    }

    private function doRotate(): void {
        if ($this->rotateLogs) {
            $archiveFile = $this->logFile . '.' . date('Ymd_His');
            if (!@rename($this->logFile, $archiveFile)) {
                // Datei wurde möglicherweise bereits durch einen anderen Prozess rotiert
                if (file_exists($this->logFile)) {
                    $this->handleWriteError("Fehler beim Rotieren der Logdatei");
                }
                return;
            }
        } else {
            if (@file_put_contents($this->logFile, "\xEF\xBB\xBF") === false) {
                $this->handleWriteError("Fehler beim Leeren der Logdatei");
            }
        }

        // Neue Logdatei mit korrekten Berechtigungen erstellen
        if (!file_exists($this->logFile)) {
            @file_put_contents($this->logFile, "\xEF\xBB\xBF");
            @chmod($this->logFile, $this->filePermissions);
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
