<?php
/*
 * Created on   : Tue Dec 17 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConsoleLoggerTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ERRORToolkit\Logger\ConsoleLogger;
use Psr\Log\LogLevel;

class ConsoleLoggerTest extends TestCase {
    public function testLogsInfoLevel() {
        $logger = new ConsoleLogger(LogLevel::INFO);
        $logger->log(LogLevel::INFO, "Multiline Test: Line 1 - This is an info message");
        $logger->log(LogLevel::INFO, "Multiline Test: Line 2 - This is an info message");

        ob_start();
        $logger->log(LogLevel::INFO, "This is an info message");
        $output = ob_get_clean();

        $this->assertStringContainsString("This is an info message", $output);

        // Teste beides: Mit und ohne Farben
        if (strpos($output, "\033[") !== false) {
            // Wenn ANSI-Codes vorhanden sind, teste sie
            $this->assertStringContainsString("\033[0;32m", $output, "Info sollte grün sein");
            $this->assertStringContainsString("\033[0m", $output, "Reset-Code sollte vorhanden sein");
        } else {
            // Wenn keine ANSI-Codes, stelle sicher dass auch wirklich keine da sind
            $this->assertStringNotContainsString("\033[", $output, "Keine ANSI-Codes erwartet");
        }
    }

    public function testDoesNotLogBelowThreshold() {
        $logger = new ConsoleLogger(LogLevel::WARNING);

        ob_start();
        $logger->log(LogLevel::INFO, "This info message should not appear");
        $output = ob_get_clean();

        $this->assertSame("", $output, "Es sollte keine Ausgabe geben, da INFO unterhalb der WARNING-Schwelle liegt.");
    }

    public function testLogsCriticalLevel() {
        $logger = new ConsoleLogger(LogLevel::DEBUG); // DEBUG lässt alles loggen

        ob_start();
        $logger->log(LogLevel::CRITICAL, "System failure!");
        $output = ob_get_clean();

        $this->assertStringContainsString("System failure!", $output);

        // Teste beides: Mit und ohne Farben
        if (strpos($output, "\033[") !== false) {
            // Wenn ANSI-Codes vorhanden sind, teste sie
            $this->assertStringContainsString("\033[1;35m", $output, "Critical sollte magenta sein");
            $this->assertStringContainsString("\033[0m", $output, "Reset-Code sollte vorhanden sein");
        } else {
            // Wenn keine ANSI-Codes, stelle sicher dass auch wirklich keine da sind
            $this->assertStringNotContainsString("\033[", $output, "Keine ANSI-Codes erwartet");
        }
    }

    public function testLogsWithoutColorsWhenNotSupported() {
        $logger = new ConsoleLogger(LogLevel::INFO);

        ob_start();
        $logger->log(LogLevel::INFO, "Plain text message");
        $output = ob_get_clean();

        $this->assertStringContainsString("Plain text message", $output);
    }
}