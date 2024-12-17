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
            try {
                file_put_contents($logFile, ""); // Leere Datei erstellen
            } catch (Exception $e) {
                throw new Exception("Fehler beim Erstellen der Logdatei: " . $e->getMessage());
            }
        }
    }

    protected function writeLog(string $logEntry, string $level): void {
        try {
            file_put_contents($this->logFile, $logEntry . PHP_EOL, FILE_APPEND);
        } catch (Exception $e) {
            throw new Exception("Fehler beim Schreiben in die Logdatei: " . $e->getMessage());
        }
    }
}
