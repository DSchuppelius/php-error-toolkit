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

    /** @return resource */
    private static function createStream() {
        return fopen('php://memory', 'r+');
    }

    private static function readStream($stream): string {
        rewind($stream);
        return stream_get_contents($stream) ?: '';
    }

    public function testLogsInfoLevel() {
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::INFO, stream: $stream);

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

        $stream2 = self::createStream();
        $logger2 = new ConsoleLogger(LogLevel::INFO, stream: $stream2);
        $logger2->log(LogLevel::INFO, "This is an info message");
        $logger2->flushDuplicates();
        $output = self::readStream($stream2);

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
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::WARNING, enableDeduplication: false, stream: $stream);

        $logger->log(LogLevel::INFO, "This info message should not appear");
        $output = self::readStream($stream);

        $this->assertSame("", $output, "Es sollte keine Ausgabe geben, da INFO unterhalb der WARNING-Schwelle liegt.");
    }

    public function testLogsCriticalLevel() {
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::DEBUG, enableDeduplication: false, stream: $stream);

        $logger->log(LogLevel::CRITICAL, "System failure!");
        $output = self::readStream($stream);

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
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: false, stream: $stream);

        $logger->log(LogLevel::INFO, "Plain text message");
        $output = self::readStream($stream);

        $this->assertStringContainsString("Plain text message", $output);
    }

    public function testDeduplicationPreventsDuplicateLogs() {
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: true, stream: $stream);

        // Logge die gleiche Nachricht 5 mal
        for ($i = 0; $i < 5; $i++) {
            $logger->log(LogLevel::INFO, "Duplicate message");
        }
        // Flush um den letzten Eintrag auszugeben
        $logger->flushDuplicates();
        $output = self::readStream($stream);

        // Es sollte nur ein Eintrag mit (x5) vorhanden sein
        $this->assertStringContainsString("Duplicate message (x5)", $output);
        // Zähle die Anzahl der "Duplicate message" Vorkommen - sollte nur 1 sein
        $this->assertSame(1, substr_count($output, "Duplicate message"));
    }

    public function testDeduplicationAllowsDifferentMessages() {
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: true, stream: $stream);

        $logger->log(LogLevel::INFO, "Message A");
        $logger->log(LogLevel::INFO, "Message B");
        $logger->log(LogLevel::INFO, "Message A");
        $logger->flushDuplicates();
        $output = self::readStream($stream);

        // Alle drei sollten einzeln geloggt werden (keine Duplikate hintereinander)
        $this->assertStringContainsString("Message A", $output);
        $this->assertStringContainsString("Message B", $output);
        // Keine (xN) Suffixe bei unterschiedlichen Nachrichten
        $this->assertStringNotContainsString("(x", $output);
    }

    public function testDeduplicationCanBeDisabled() {
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: false, stream: $stream);
        $this->assertFalse($logger->isDeduplicationEnabled());

        $logger->log(LogLevel::INFO, "Same message");
        $logger->log(LogLevel::INFO, "Same message");
        $logger->log(LogLevel::INFO, "Same message");
        $output = self::readStream($stream);

        // Alle drei sollten einzeln geloggt werden
        $this->assertSame(3, substr_count($output, "Same message"));
        $this->assertStringNotContainsString("(x", $output);
    }

    public function testSetDeduplicationFlushesOnDisable() {
        $stream = self::createStream();
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: true, stream: $stream);

        $logger->log(LogLevel::INFO, "Repeated");
        $logger->log(LogLevel::INFO, "Repeated");
        $logger->log(LogLevel::INFO, "Repeated");
        // Deaktiviere Deduplizierung - sollte ausstehende Duplikate flushen
        $logger->setDeduplication(false);
        $output = self::readStream($stream);

        $this->assertStringContainsString("Repeated (x3)", $output);
    }

    public function testIsDeduplicationEnabled() {
        $logger = new ConsoleLogger(LogLevel::INFO, enableDeduplication: true);
        $this->assertTrue($logger->isDeduplicationEnabled());

        $logger->setDeduplication(false);
        $this->assertFalse($logger->isDeduplicationEnabled());
    }
}
