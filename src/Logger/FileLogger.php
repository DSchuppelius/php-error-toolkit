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
use Psr\Log\LogLevel;

class FileLogger extends LoggerAbstract {
    protected string $logFile;

    public function __construct(?string $logFile = null, string $logLevel = LogLevel::DEBUG, bool $failSafe = true) {
        parent::__construct($logLevel);

        if (is_null($logFile) || ($failSafe && !is_writable(dirname($logFile)))) {
            $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'default.log';
        }

        $this->logFile = $logFile;

        if (!file_exists($logFile)) {
            if (@file_put_contents($logFile, "") === false) {
                $error = error_get_last();
                $message = $error['message'] ?? 'Unbekannter Fehler beim Erstellen der Logdatei';
                throw new FileNotWrittenException("Fehler beim Erstellen der Logdatei: " . $message);
            }
        }
    }

    protected function writeLog(string $logEntry, string $level): void {
        if (@file_put_contents($this->logFile, $logEntry . PHP_EOL, FILE_APPEND) === false) {
            $error = error_get_last();
            $message = $error['message'] ?? 'Unbekannter Fehler beim Schreiben in die Logdatei';
            throw new FileNotWrittenException("Fehler beim Schreiben in die Logdatei: " . $message);
        }
    }

    public function getLogFile(): string {
        return $this->logFile;
    }
}