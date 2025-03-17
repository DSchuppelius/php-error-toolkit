<?php
/*
 * Created on   : Tue Dec 17 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileLoggerTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

use ERRORToolkit\Exceptions\FileSystem\FileNotWrittenException;
use PHPUnit\Framework\TestCase;
use ERRORToolkit\Logger\FileLogger;
use Psr\Log\LogLevel;

class FileLoggerTest extends TestCase {
    private string $testLogFile;

    protected function setUp(): void {
        // Erzeuge eine temporäre Logdatei für Testzwecke
        $this->testLogFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_' . uniqid() . '.log';
    }

    protected function tearDown(): void {
        // Entfernt die Testdatei nach dem Test, sofern vorhanden
        if (file_exists($this->testLogFile)) {
            @chmod($this->testLogFile, 0666);
            if (!@unlink($this->testLogFile)) {
                $this->fail("Die Testdatei konnte nicht gelöscht werden, obwohl die Rechte zurückgesetzt wurden.");
            }
        }
    }

    public function testLogsAtOrAboveThreshold() {
        $logger = new FileLogger($this->testLogFile, LogLevel::WARNING);

        $logger->log(LogLevel::INFO, "This is an info message");

        $logger->log(LogLevel::WARNING, "This is a warning message");

        $logger->log(LogLevel::ERROR, "This is an error message");

        $logContent = file_get_contents($this->testLogFile);

        $this->assertStringNotContainsString("This is an info message", $logContent, "INFO sollte nicht geloggt werden, da unterhalb WARNING.");
        $this->assertStringContainsString("This is a warning message", $logContent, "WARNING sollte geloggt werden.");
        $this->assertStringContainsString("This is an error message", $logContent, "ERROR sollte geloggt werden.");
    }

    public function testUsesDefaultLogFileIfNoneProvided() {
        $logger = new FileLogger(null, LogLevel::DEBUG);
        $logger->log(LogLevel::DEBUG, "Message in default file");

        $defaultLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'default.log';

        $this->assertFileExists($defaultLog, "Default-Logdatei sollte erstellt werden.");
        $this->assertStringContainsString("Message in default file", file_get_contents($defaultLog));

        unlink($defaultLog);
    }

    public function testFileCreationFailureThrowsException() {
        $nonWritableDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'no_write_' . uniqid();
        if (!mkdir($nonWritableDir) && !is_dir($nonWritableDir)) {
            $this->markTestSkipped("Konnte kein temporäres Verzeichnis erstellen, Test wird übersprungen.");
            return;
        }

        if (!chmod($nonWritableDir, 0500)) {
            rmdir($nonWritableDir);
            $this->markTestSkipped("Konnte die Zugriffsrechte für das Verzeichnis nicht ändern, Test wird übersprungen.");
            return;
        }

        // Zusätzlicher Test: Überprüfen, ob eine Datei erstellt werden kann
        $testFile = $nonWritableDir . DIRECTORY_SEPARATOR . 'test.log';
        $testWriteResult = @file_put_contents($testFile, 'test');
        if ($testWriteResult !== false || file_exists($testFile)) {
            chmod($nonWritableDir, 0700);
            if (file_exists($testFile)) {
                unlink($testFile);
            }
            rmdir($nonWritableDir);
            $this->markTestSkipped("Das Verzeichnis konnte nicht als nicht beschreibbar getestet werden.");
            return;
        }
        $nonWritableLogFile = $nonWritableDir . DIRECTORY_SEPARATOR . 'no_permission.log';

        $this->expectException(FileNotWrittenException::class);
        $this->expectExceptionMessageMatches("/Fehler beim Erstellen der Logdatei/");

        try {
            new FileLogger($nonWritableLogFile, LogLevel::DEBUG, false);
        } finally {
            chmod($nonWritableDir, 0700);
            if (file_exists($nonWritableLogFile)) {
                unlink($nonWritableLogFile);
            }
            rmdir($nonWritableDir);
        }
    }

    public function testWriteFailureThrowsException() {
        // Legt eine Logdatei an und entzieht die Schreibrechte, um den Schreibfehler zu simulieren.
        file_put_contents($this->testLogFile, "");
        chmod($this->testLogFile, 0400); // Nur Lese-Rechte

        $logger = new FileLogger($this->testLogFile, LogLevel::DEBUG);

        $this->expectException(FileNotWrittenException::class);
        $this->expectExceptionMessageMatches("/Logdatei ist nicht beschreibbar/");

        // Versucht in eine Datei zu schreiben, für die keine Schreibrechte bestehen
        $logger->log(LogLevel::ERROR, "Should fail");
    }
}
