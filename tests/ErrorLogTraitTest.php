<?php
/*
 * Created on   : Fri Jan 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ErrorLogTraitTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use ERRORToolkit\Traits\ErrorLog;
use ERRORToolkit\Logger\ConsoleLogger;
use ERRORToolkit\LoggerRegistry;
use Exception;
use Psr\Log\LogLevel;
use RuntimeException;
use InvalidArgumentException;

/**
 * Testklasse die das ErrorLog Trait verwendet
 */
class ErrorLogTestClass {
    use ErrorLog;
}

class ErrorLogTraitTest extends TestCase {
    private ErrorLogTestClass $testInstance;

    protected function setUp(): void {
        $this->testInstance = new ErrorLogTestClass();
        // Logger auf DEBUG setzen um alle Nachrichten zu erfassen
        $logger = new ConsoleLogger(LogLevel::DEBUG);
        LoggerRegistry::setLogger($logger);
        ErrorLogTestClass::setLogger($logger);
    }

    protected function tearDown(): void {
        LoggerRegistry::resetLogger();
    }

    /**
     * Test: logInfo() als Instanzmethode
     */
    public function testLogInfoInstance(): void {
        ob_start();
        $this->testInstance->logInfo("Test info message");
        $output = ob_get_clean();

        $this->assertStringContainsString("Test info message", $output);
        $this->assertStringContainsString("info", strtolower($output));
    }

    /**
     * Test: logError() als Instanzmethode
     */
    public function testLogErrorInstance(): void {
        ob_start();
        $this->testInstance->logError("Test error message");
        $output = ob_get_clean();

        $this->assertStringContainsString("Test error message", $output);
        $this->assertStringContainsString("error", strtolower($output));
    }

    /**
     * Test: logWarning() als Instanzmethode
     */
    public function testLogWarningInstance(): void {
        ob_start();
        $this->testInstance->logWarning("Test warning message");
        $output = ob_get_clean();

        $this->assertStringContainsString("Test warning message", $output);
        $this->assertStringContainsString("warning", strtolower($output));
    }

    /**
     * Test: logDebug() als Instanzmethode
     */
    public function testLogDebugInstance(): void {
        ob_start();
        $this->testInstance->logDebug("Test debug message");
        $output = ob_get_clean();

        $this->assertStringContainsString("Test debug message", $output);
        $this->assertStringContainsString("debug", strtolower($output));
    }

    /**
     * Test: logCritical() als Instanzmethode
     */
    public function testLogCriticalInstance(): void {
        ob_start();
        $this->testInstance->logCritical("Test critical message");
        $output = ob_get_clean();

        $this->assertStringContainsString("Test critical message", $output);
        $this->assertStringContainsString("critical", strtolower($output));
    }

    /**
     * Test: logInfo() als statische Methode
     */
    public function testLogInfoStatic(): void {
        ob_start();
        ErrorLogTestClass::logInfo("Static info message");
        $output = ob_get_clean();

        $this->assertStringContainsString("Static info message", $output);
        $this->assertStringContainsString("info", strtolower($output));
    }

    /**
     * Test: logError() als statische Methode
     */
    public function testLogErrorStatic(): void {
        ob_start();
        ErrorLogTestClass::logError("Static error message");
        $output = ob_get_clean();

        $this->assertStringContainsString("Static error message", $output);
        $this->assertStringContainsString("error", strtolower($output));
    }

    /**
     * Test: logErrorAndThrow() als Instanzmethode
     */
    public function testLogErrorAndThrowInstance(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Error that throws");

        ob_start();
        try {
            $this->testInstance->logErrorAndThrow(RuntimeException::class, "Error that throws");
        } finally {
            $output = ob_get_clean();
            $this->assertStringContainsString("Error that throws", $output);
            $this->assertStringContainsString("error", strtolower($output));
        }
    }

    /**
     * Test: logWarningAndThrow() als Instanzmethode
     */
    public function testLogWarningAndThrowInstance(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Warning that throws");

        ob_start();
        try {
            $this->testInstance->logWarningAndThrow(InvalidArgumentException::class, "Warning that throws");
        } finally {
            $output = ob_get_clean();
            $this->assertStringContainsString("Warning that throws", $output);
            $this->assertStringContainsString("warning", strtolower($output));
        }
    }

    /**
     * Test: logCriticalAndThrow() als statische Methode
     */
    public function testLogCriticalAndThrowStatic(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Critical static error");

        ob_start();
        try {
            ErrorLogTestClass::logCriticalAndThrow(RuntimeException::class, "Critical static error");
        } finally {
            $output = ob_get_clean();
            $this->assertStringContainsString("Critical static error", $output);
            $this->assertStringContainsString("critical", strtolower($output));
        }
    }

    /**
     * Test: logAndThrow mit Kontext
     */
    public function testLogAndThrowWithContext(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Error with context");

        ob_start();
        try {
            $this->testInstance->logErrorAndThrow(
                RuntimeException::class,
                "Error with context",
                ['user_id' => 123, 'action' => 'test']
            );
        } finally {
            $output = ob_get_clean();
            $this->assertStringContainsString("Error with context", $output);
        }
    }

    /**
     * Test: logAndThrow mit vorheriger Exception (Chaining)
     */
    public function testLogAndThrowWithPreviousException(): void {
        $previousException = new Exception("Previous error");

        try {
            ob_start();
            $this->testInstance->logErrorAndThrow(
                RuntimeException::class,
                "Chained error",
                [],
                $previousException
            );
        } catch (RuntimeException $e) {
            ob_get_clean();
            $this->assertSame("Chained error", $e->getMessage());
            $this->assertSame($previousException, $e->getPrevious());
            return;
        }

        $this->fail("RuntimeException was not thrown");
    }

    /**
     * Test: Ungültige Methode wirft BadMethodCallException (Instanz)
     */
    public function testInvalidMethodThrowsBadMethodCallExceptionInstance(): void {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/existiert nicht/');

        $this->testInstance->logInvalidMethod("test");
    }

    /**
     * Test: Ungültige Methode wirft BadMethodCallException (statisch)
     */
    public function testInvalidMethodThrowsBadMethodCallExceptionStatic(): void {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/existiert nicht/');

        ErrorLogTestClass::logInvalidMethod("test");
    }

    /**
     * Test: Alle Log-Level als Instanzmethoden
     */
    public function testAllLogLevelsInstance(): void {
        $levels = ['Debug', 'Info', 'Notice', 'Warning', 'Error', 'Critical', 'Alert', 'Emergency'];

        foreach ($levels as $level) {
            $method = 'log' . $level;
            $message = "Test {$level} message";

            ob_start();
            $this->testInstance->$method($message);
            $output = ob_get_clean();

            $this->assertStringContainsString($message, $output, "Level {$level} sollte geloggt werden");
        }
    }

    /**
     * Test: Alle Log-Level als statische Methoden
     */
    public function testAllLogLevelsStatic(): void {
        $levels = ['Debug', 'Info', 'Notice', 'Warning', 'Error', 'Critical', 'Alert', 'Emergency'];

        foreach ($levels as $level) {
            $method = 'log' . $level;
            $message = "Static test {$level} message";

            ob_start();
            ErrorLogTestClass::$method($message);
            $output = ob_get_clean();

            $this->assertStringContainsString($message, $output, "Level {$level} sollte statisch geloggt werden");
        }
    }

    /**
     * Test: Alle LogAndThrow-Varianten
     */
    public function testAllLogAndThrowLevels(): void {
        $levels = ['Debug', 'Info', 'Notice', 'Warning', 'Error', 'Critical', 'Alert', 'Emergency'];

        foreach ($levels as $level) {
            $method = 'log' . $level . 'AndThrow';
            $message = "AndThrow test for {$level}";

            try {
                ob_start();
                $this->testInstance->$method(RuntimeException::class, $message);
                ob_get_clean();
                $this->fail("Exception sollte geworfen werden für {$method}");
            } catch (RuntimeException $e) {
                $output = ob_get_clean();
                $this->assertSame($message, $e->getMessage());
                $this->assertStringContainsString($message, $output);
            }
        }
    }
}
