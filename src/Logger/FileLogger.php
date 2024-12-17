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
use Exception;
use Psr\Log\LogLevel;

class FileLogger extends LoggerAbstract {
    protected string $logFile;

    public function __construct(?string $logFile = null, string $logLevel = LogLevel::DEBUG) {
        parent::__construct($logLevel);

        if (is_null($logFile) || !is_writable(dirname($logFile))) {
            $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'default.log';
        }

        $this->logFile = $logFile;

        if (!file_exists($logFile)) {
            $result = @file_put_contents($logFile, "");
            if ($result === false) {
                $error = error_get_last();
                $message = $error['message'] ?? 'Unbekannter Fehler beim Erstellen der Logdatei';
                throw new Exception("Fehler beim Erstellen der Logdatei: " . $message);
            }
        }
    }

    protected function writeLog(string $logEntry, string $level): void {
        $result = @file_put_contents($this->logFile, $logEntry . PHP_EOL, FILE_APPEND);
        if ($result === false) {
            $error = error_get_last();
            $message = $error['message'] ?? 'Unbekannter Fehler beim Schreiben in die Logdatei';
            throw new Exception("Fehler beim Schreiben in die Logdatei: " . $message);
        }
    }
}
