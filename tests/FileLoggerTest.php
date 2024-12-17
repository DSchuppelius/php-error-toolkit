<?php
/*
 * Created on   : Tue Dec 17 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileLoggerTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

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

        // INFO liegt unterhalb WARNING, sollte also nicht geloggt werden.
        $logger->log(LogLevel::INFO, "This is an info message");

        // WARNING liegt auf Schwelle, sollte geloggt werden.
        $logger->log(LogLevel::WARNING, "This is a warning message");

        // ERROR liegt über der Schwelle, sollte geloggt werden.
        $logger->log(LogLevel::ERROR, "This is an error message");

        $logContent = file_get_contents($this->testLogFile);

        $this->assertStringNotContainsString("This is an info message", $logContent, "INFO sollte nicht geloggt werden, da unterhalb WARNING.");
        $this->assertStringContainsString("This is a warning message", $logContent, "WARNING sollte geloggt werden.");
        $this->assertStringContainsString("This is an error message", $logContent, "ERROR sollte geloggt werden.");
    }

    public function testUsesDefaultLogFileIfNoneProvided() {
        $logger = new FileLogger(null, LogLevel::DEBUG);
        $logger->log(LogLevel::DEBUG, "Message in default file");

        // Der Default-Logpfad sollte sich im Systemtemp-Verzeichnis befinden
        // Der Konstruktor wählt automatisch: sys_get_temp_dir().'/default.log'
        $defaultLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'default.log';

        $this->assertFileExists($defaultLog, "Default-Logdatei sollte erstellt werden.");
        $this->assertStringContainsString("Message in default file", file_get_contents($defaultLog));

        // Aufräumen, um keine Datei zurückzulassen
        unlink($defaultLog);
    }

    public function testFileCreationFailureThrowsException() {
        // Erzeuge einen Pfad, der hoffentlich nicht beschreibbar ist (z. B. Wurzelverzeichnis).
        // Dieser Test ist ggf. plattformabhängig, auf manchen Systemen ist / nicht beschreibbar.
        // Alternativ kann man einen Pfad wählen, bei dem man sicher ist, dass er nicht beschreibbar ist.
        $nonWritableDir = '/root';
        if (!is_dir($nonWritableDir) || is_writable($nonWritableDir)) {
            $this->markTestSkipped("Dieser Test konnte nicht ausgeführt werden, da das gewählte Verzeichnis beschreibbar ist oder nicht existiert.");
        }

        $nonWritableLogFile = $nonWritableDir . DIRECTORY_SEPARATOR . 'no_permission.log';

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/Fehler beim Erstellen der Logdatei/");

        // Versucht eine Logdatei in ein nicht schreibbares Verzeichnis zu legen
        new FileLogger($nonWritableLogFile, LogLevel::DEBUG);
    }

    public function testWriteFailureThrowsException() {
        // Legt eine Logdatei an und entzieht die Schreibrechte, um den Schreibfehler zu simulieren.
        file_put_contents($this->testLogFile, "");
        chmod($this->testLogFile, 0400); // Nur Lese-Rechte

        $logger = new FileLogger($this->testLogFile, LogLevel::DEBUG);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches("/Fehler beim Schreiben in die Logdatei/");

        // Versucht in eine Datei zu schreiben, für die keine Schreibrechte bestehen
        $logger->log(LogLevel::ERROR, "Should fail");
    }
}
