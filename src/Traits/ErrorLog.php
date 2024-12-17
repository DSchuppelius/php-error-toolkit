<?php
/*
 * Created on   : Fri Oct 25 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ErrorLog.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace ERRORToolkit\Traits;

use Psr\Log\LoggerInterface;

trait ErrorLog {
    protected ?LoggerInterface $logger = null;

    protected function logDebug(string $message, array $context = []): void {
        if (!is_null($this->logger)) {
            $this->logger->debug($message, $context);
        } else {
            error_log("Debug: $message");
        }
    }

    protected function logInfo(string $message, array $context = []): void {
        if (!is_null($this->logger)) {
            $this->logger->info($message, $context);
        } else {
            error_log("Info: $message");
        }
    }

    protected function logWarning(string $message, array $context = []): void {
        if (!is_null($this->logger)) {
            $this->logger->warning($message, $context);
        } else {
            error_log("Warning: $message");
        }
    }

    protected function logError(string $message, array $context = []): void {
        if (!is_null($this->logger)) {
            $this->logger->error($message, $context);
        } else {
            error_log("Error: $message");
        }
    }
}