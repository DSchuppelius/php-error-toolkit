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

        // Diese Zeilen werden direkt auf der Konsole angezeigt (Deduplizierung aktiv)
        // Line 1 → gepuffert
        // Line 2 → Line 1 ausgegeben, Line 2 gepuffert
        // Line 2 → Duplikat, Zähler +1
        // Line 3 → Line 2 (x2) ausgegeben, Line 3 gepuffert
        $logger->log(LogLevel::INFO, "Multiline Test: Line 1 - This is an info message");
        $logger->log(LogLevel::INFO, "Multiline Test: Line 2 - This is an info message");
        $logger->log(LogLevel::INFO, "Multiline Test: Line 2 - This is an info message");
        $logger->log(LogLevel::INFO, "Multiline Test: Line 3 - Trigger output");
        $logger->flushDuplicates(); // Line 3 ausgeben

        ob_start();
        $logger->log(LogLevel::INFO, "This is an info message");
        $logger->flushDuplicates();
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
        $logger = new ConsoleLogger(LogLevel::WARNING, enableDeduplication: false);

        ob_start();
        $logger->log(LogLevel::INFO, "This info message should not appear");
        $output = ob_get_clean();

        $this->assertSame("", $output, "Es sollte keine Ausgabe geben, da INFO unterhalb der WARNING-Schwelle liegt.");
    }

    public function testLogsCriticalLevel() {
        $logger = new ConsoleLogger(LogLevel::DEBUG, enableDeduplication: false); // DEBUG lässt alles loggen

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
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: false);

        ob_start();
        $logger->log(LogLevel::INFO, "Plain text message");
        $output = ob_get_clean();

        $this->assertStringContainsString("Plain text message", $output);
    }

    public function testDeduplicationPreventsDuplicateLogs() {
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: true);

        ob_start();
        // Logge die gleiche Nachricht 5 mal
        for ($i = 0; $i < 5; $i++) {
            $logger->log(LogLevel::INFO, "Duplicate message");
        }
        // Flush um den letzten Eintrag auszugeben
        $logger->flushDuplicates();
        $output = ob_get_clean();

        // Es sollte nur ein Eintrag mit (x5) vorhanden sein
        $this->assertStringContainsString("Duplicate message (x5)", $output);
        // Zähle die Anzahl der "Duplicate message" Vorkommen - sollte nur 1 sein
        $this->assertSame(1, substr_count($output, "Duplicate message"));
    }

    public function testDeduplicationAllowsDifferentMessages() {
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: true);

        ob_start();
        $logger->log(LogLevel::INFO, "Message A");
        $logger->log(LogLevel::INFO, "Message B");
        $logger->log(LogLevel::INFO, "Message A");
        $logger->flushDuplicates();
        $output = ob_get_clean();

        // Alle drei sollten einzeln geloggt werden (keine Duplikate hintereinander)
        $this->assertStringContainsString("Message A", $output);
        $this->assertStringContainsString("Message B", $output);
        // Keine (xN) Suffixe bei unterschiedlichen Nachrichten
        $this->assertStringNotContainsString("(x", $output);
    }

    public function testDeduplicationCanBeDisabled() {
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: false);
        $this->assertFalse($logger->isDeduplicationEnabled());

        ob_start();
        $logger->log(LogLevel::INFO, "Same message");
        $logger->log(LogLevel::INFO, "Same message");
        $logger->log(LogLevel::INFO, "Same message");
        $output = ob_get_clean();

        // Alle drei sollten einzeln geloggt werden
        $this->assertSame(3, substr_count($output, "Same message"));
        $this->assertStringNotContainsString("(x", $output);
    }

    public function testSetDeduplicationFlushesOnDisable() {
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: true);

        ob_start();
        $logger->log(LogLevel::INFO, "Repeated");
        $logger->log(LogLevel::INFO, "Repeated");
        $logger->log(LogLevel::INFO, "Repeated");
        // Deaktiviere Deduplizierung - sollte ausstehende Duplikate flushen
        $logger->setDeduplication(false);
        $output = ob_get_clean();

        $this->assertStringContainsString("Repeated (x3)", $output);
    }

    public function testIsDeduplicationEnabled() {
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: true);
        $this->assertTrue($logger->isDeduplicationEnabled());

        $logger->setDeduplication(false);
        $this->assertFalse($logger->isDeduplicationEnabled());
    }
}
