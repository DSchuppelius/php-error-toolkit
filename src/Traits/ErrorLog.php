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

    protected function logNotice(string $message, array $context = []): void {
        if (!is_null($this->logger)) {
            $this->logger->notice($message, $context);
        } else {
            error_log("Notice: $message");
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

    protected function logCritical(string $message, array $context = []): void {
        if (!is_null($this->logger)) {
            $this->logger->critical($message, $context);
        } else {
            error_log("Critical: $message");
        }
    }

    protected function logAlert(string $message, array $context = []): void {
        if (!is_null($this->logger)) {
            $this->logger->alert($message, $context);
        } else {
            error_log("Alert: $message");
        }
    }
}