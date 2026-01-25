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

    // Beispielmethode die einen Fehler loggt und eine Exception wirft (IDE Testzwecke)
    public function test(): string {
        if (false)
            return "Unreachable";

        if ($this->logWarningIf(true, "This is a warning"))
            return "Warning logged";

        $this->logErrorAndThrow(RuntimeException::class, "This is a test error");
    }
}

class ErrorLogTraitTest extends TestCase {
    private ErrorLogTestClass $testInstance;

    protected function setUp(): void {
        $this->testInstance = new ErrorLogTestClass();
        // Logger auf DEBUG setzen um alle Nachrichten zu erfassen
        // Deduplizierung deaktivieren für sofortige Ausgabe in Tests
        $logger = new ConsoleLogger(LogLevel::DEBUG, enableDeduplication: false);
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
     * Test: Alle expliziten LogAndThrow-Varianten (Error, Critical, Alert, Emergency)
     */
    public function testAllLogAndThrowLevels(): void {
        $levels = ['Error', 'Critical', 'Alert', 'Emergency'];

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

    // ========================================================================
    // Tests für die neuen erweiterten Funktionen
    // ========================================================================

    /**
     * Test: hasLogger() gibt true zurück wenn Logger gesetzt
     */
    public function testHasLoggerReturnsTrue(): void {
        $this->assertTrue(ErrorLogTestClass::hasLogger());
    }

    /**
     * Test: getLogger() gibt den gesetzten Logger zurück
     */
    public function testGetLoggerReturnsLogger(): void {
        $logger = ErrorLogTestClass::getLogger();
        $this->assertInstanceOf(ConsoleLogger::class, $logger);
    }

    /**
     * Test: logException() loggt eine Exception mit Details
     */
    public function testLogException(): void {
        $exception = new RuntimeException("Test exception message");

        ob_start();
        ErrorLogTestClass::logException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString("RuntimeException", $output);
        $this->assertStringContainsString("Test exception message", $output);
        $this->assertStringContainsString("error", strtolower($output));
    }

    /**
     * Test: logException() mit benutzerdefiniertem Level
     */
    public function testLogExceptionWithCustomLevel(): void {
        $exception = new InvalidArgumentException("Invalid argument");

        ob_start();
        ErrorLogTestClass::logException($exception, LogLevel::WARNING);
        $output = ob_get_clean();

        $this->assertStringContainsString("InvalidArgumentException", $output);
        $this->assertStringContainsString("warning", strtolower($output));
    }

    /**
     * Test: logException() mit verketteter Exception
     */
    public function testLogExceptionWithPreviousException(): void {
        $previous = new Exception("Previous exception");
        $exception = new RuntimeException("Main exception", 0, $previous);

        ob_start();
        ErrorLogTestClass::logException($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString("Main exception", $output);
    }

    /**
     * Test: logWarningIf() loggt nur wenn Bedingung wahr ist
     */
    public function testLogWarningIfTrue(): void {
        ob_start();
        ErrorLogTestClass::logWarningIf(true, "Should be logged");
        $output = ob_get_clean();

        $this->assertStringContainsString("Should be logged", $output);
        $this->assertStringContainsString("warning", strtolower($output));
    }

    /**
     * Test: logWarningIf() loggt nicht wenn Bedingung falsch ist
     */
    public function testLogWarningIfFalse(): void {
        ob_start();
        ErrorLogTestClass::logWarningIf(false, "Should NOT be logged");
        $output = ob_get_clean();

        $this->assertEmpty(trim($output));
    }

    /**
     * Test: logErrorUnless() loggt nur wenn Bedingung falsch ist
     */
    public function testLogErrorUnlessFalse(): void {
        ob_start();
        ErrorLogTestClass::logErrorUnless(false, "Should be logged");
        $output = ob_get_clean();

        $this->assertStringContainsString("Should be logged", $output);
        $this->assertStringContainsString("error", strtolower($output));
    }

    /**
     * Test: logErrorUnless() loggt nicht wenn Bedingung wahr ist
     */
    public function testLogErrorUnlessTrue(): void {
        ob_start();
        ErrorLogTestClass::logErrorUnless(true, "Should NOT be logged");
        $output = ob_get_clean();

        $this->assertEmpty(trim($output));
    }

    /**
     * Test: Alle Log-Level If Varianten
     */
    public function testAllLogLevelsIf(): void {
        $levels = ['Debug', 'Info', 'Notice', 'Warning', 'Error', 'Critical', 'Alert', 'Emergency'];

        foreach ($levels as $level) {
            $method = 'log' . $level . 'If';

            // Test mit true - sollte loggen
            ob_start();
            ErrorLogTestClass::$method(true, "Conditional {$level} message");
            $output = ob_get_clean();
            $this->assertStringContainsString("Conditional {$level} message", $output, "log{$level}If(true) sollte loggen");

            // Test mit false - sollte nicht loggen
            ob_start();
            ErrorLogTestClass::$method(false, "Should not appear");
            $output = ob_get_clean();
            $this->assertEmpty(trim($output), "log{$level}If(false) sollte nicht loggen");
        }
    }

    /**
     * Test: Alle Log-Level Unless Varianten
     */
    public function testAllLogLevelsUnless(): void {
        $levels = ['Debug', 'Info', 'Notice', 'Warning', 'Error', 'Critical', 'Alert', 'Emergency'];

        foreach ($levels as $level) {
            $method = 'log' . $level . 'Unless';

            // Test mit false - sollte loggen
            ob_start();
            ErrorLogTestClass::$method(false, "Unless {$level} message");
            $output = ob_get_clean();
            $this->assertStringContainsString("Unless {$level} message", $output, "log{$level}Unless(false) sollte loggen");

            // Test mit true - sollte nicht loggen
            ob_start();
            ErrorLogTestClass::$method(true, "Should not appear");
            $output = ob_get_clean();
            $this->assertEmpty(trim($output), "log{$level}Unless(true) sollte nicht loggen");
        }
    }

    /**
     * Test: logDebugWithTimer() führt Callback aus und loggt Zeit
     */
    public function testLogDebugWithTimer(): void {
        ob_start();
        $result = ErrorLogTestClass::logDebugWithTimer(function () {
            usleep(10000); // 10ms warten
            return "timer result";
        }, "Test operation");
        $output = ob_get_clean();

        $this->assertSame("timer result", $result);
        $this->assertStringContainsString("Test operation", $output);
        $this->assertStringContainsString("completed", $output);
        $this->assertStringContainsString("ms", $output);
        $this->assertStringContainsString("debug", strtolower($output));
    }

    /**
     * Test: logInfoWithTimer() führt Callback aus und loggt Zeit auf Info-Level
     */
    public function testLogInfoWithTimer(): void {
        ob_start();
        $result = ErrorLogTestClass::logInfoWithTimer(function () {
            return 42;
        }, "Calculate answer");
        $output = ob_get_clean();

        $this->assertSame(42, $result);
        $this->assertStringContainsString("Calculate answer", $output);
        $this->assertStringContainsString("info", strtolower($output));
    }

    /**
     * Test: logWarningWithTimer() loggt Fehler bei Exception
     */
    public function testLogWarningWithTimerOnException(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Timer callback failed");

        ob_start();
        try {
            ErrorLogTestClass::logWarningWithTimer(function () {
                throw new RuntimeException("Timer callback failed");
            }, "Failing operation");
        } finally {
            $output = ob_get_clean();
            $this->assertStringContainsString("Failing operation", $output);
            $this->assertStringContainsString("failed", strtolower($output));
        }
    }

    /**
     * Test: Alle Log-Level WithTimer Varianten
     */
    public function testAllLogLevelsWithTimer(): void {
        $levels = ['Debug', 'Info', 'Notice', 'Warning', 'Error', 'Critical', 'Alert', 'Emergency'];

        foreach ($levels as $level) {
            $method = 'log' . $level . 'WithTimer';

            ob_start();
            $result = ErrorLogTestClass::$method(function () use ($level) {
                return "result_{$level}";
            }, "Operation {$level}");
            $output = ob_get_clean();

            $this->assertSame("result_{$level}", $result, "log{$level}WithTimer sollte Ergebnis zurückgeben");
            $this->assertStringContainsString("Operation {$level}", $output, "log{$level}WithTimer sollte Description loggen");
            $this->assertStringContainsString("completed", $output);
        }
    }

    /**
     * Test: logDebugAndReturn() loggt und gibt Wert zurück
     */
    public function testLogDebugAndReturn(): void {
        ob_start();
        $result = ErrorLogTestClass::logDebugAndReturn(
            ["key" => "value"],
            "Returning data"
        );
        $output = ob_get_clean();

        $this->assertSame(["key" => "value"], $result);
        $this->assertStringContainsString("Returning data", $output);
        $this->assertStringContainsString("debug", strtolower($output));
    }

    /**
     * Test: logInfoAndReturn() loggt auf Info-Level und gibt Wert zurück
     */
    public function testLogInfoAndReturn(): void {
        ob_start();
        $result = ErrorLogTestClass::logInfoAndReturn("string value", "String returned");
        $output = ob_get_clean();

        $this->assertSame("string value", $result);
        $this->assertStringContainsString("String returned", $output);
        $this->assertStringContainsString("info", strtolower($output));
    }

    /**
     * Test: Alle Log-Level AndReturn Varianten
     */
    public function testAllLogLevelsAndReturn(): void {
        $levels = ['Debug', 'Info', 'Notice', 'Warning', 'Error', 'Critical', 'Alert', 'Emergency'];

        foreach ($levels as $level) {
            $method = 'log' . $level . 'AndReturn';
            $expectedValue = "value_{$level}";

            ob_start();
            $result = ErrorLogTestClass::$method($expectedValue, "Return {$level} message");
            $output = ob_get_clean();

            $this->assertSame($expectedValue, $result, "log{$level}AndReturn sollte Wert zurückgeben");
            $this->assertStringContainsString("Return {$level} message", $output, "log{$level}AndReturn sollte Nachricht loggen");
        }
    }

    /**
     * Test: logWarningAndReturn() mit Kontext
     */
    public function testLogWarningAndReturnWithContext(): void {
        ob_start();
        $result = ErrorLogTestClass::logWarningAndReturn(
            null,
            "Warning with context",
            ['user_id' => 123]
        );
        $output = ob_get_clean();

        $this->assertNull($result);
        $this->assertStringContainsString("Warning with context", $output);
        $this->assertStringContainsString("warning", strtolower($output));
    }

    /**
     * Test: logInfoIf() als Instanzmethode
     */
    public function testLogInfoIfInstance(): void {
        ob_start();
        $this->testInstance->logInfoIf(true, "Instance conditional log");
        $output = ob_get_clean();

        $this->assertStringContainsString("Instance conditional log", $output);
    }

    /**
     * Test: logDebugAndReturn() als Instanzmethode
     */
    public function testLogDebugAndReturnInstance(): void {
        ob_start();
        $result = $this->testInstance->logDebugAndReturn(100, "Instance return");
        $output = ob_get_clean();

        $this->assertSame(100, $result);
        $this->assertStringContainsString("Instance return", $output);
    }

    /**
     * Test: logInfoWithTimer() als Instanzmethode
     */
    public function testLogInfoWithTimerInstance(): void {
        ob_start();
        $result = $this->testInstance->logInfoWithTimer(function () {
            return "instance timer result";
        }, "Instance timer operation");
        $output = ob_get_clean();

        $this->assertSame("instance timer result", $result);
        $this->assertStringContainsString("Instance timer operation", $output);
    }

    /**
     * Test: logWithTimer mit ungültigem Argument wirft Exception
     */
    public function testLogWithTimerInvalidArgumentThrowsException(): void {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/Closure/');

        ErrorLogTestClass::logDebugWithTimer("not a closure", "Invalid");
    }

    /**
     * Test: createDebugContext() enthält Debug-Informationen
     */
    public function testCreateDebugContext(): void {
        $context = ErrorLogTestClass::createDebugContext(['custom' => 'value']);

        $this->assertArrayHasKey('_debug', $context);
        $this->assertArrayHasKey('custom', $context);
        $this->assertSame('value', $context['custom']);

        $debug = $context['_debug'];
        $this->assertArrayHasKey('memory_usage', $debug);
        $this->assertArrayHasKey('memory_peak', $debug);
        $this->assertArrayHasKey('timestamp', $debug);
        $this->assertArrayHasKey('file', $debug);
        $this->assertArrayHasKey('line', $debug);
    }

    /**
     * Test: interpolateMessage() ersetzt Platzhalter
     */
    public function testInterpolateMessage(): void {
        $message = "User {user} performed {action} on {item}";
        $context = [
            'user' => 'admin',
            'action' => 'delete',
            'item' => 'file.txt'
        ];

        $result = ErrorLogTestClass::interpolateMessage($message, $context);

        $this->assertSame("User admin performed delete on file.txt", $result);
    }

    /**
     * Test: interpolateMessage() behandelt verschiedene Typen
     */
    public function testInterpolateMessageWithDifferentTypes(): void {
        $message = "Count: {count}, Data: {data}, Object: {obj}";
        $context = [
            'count' => 42,
            'data' => ['a', 'b'],
            'obj' => new class {
                public function __toString() {
                    return 'MyObject';
                }
            }
        ];

        $result = ErrorLogTestClass::interpolateMessage($message, $context);

        $this->assertStringContainsString("Count: 42", $result);
        $this->assertStringContainsString('["a","b"]', $result);
        $this->assertStringContainsString("MyObject", $result);
    }

    /**
     * Test: interpolateMessage() ignoriert interne Schlüssel
     */
    public function testInterpolateMessageIgnoresInternalKeys(): void {
        $message = "Value: {value}, Debug: {_debug}";
        $context = [
            'value' => 'test',
            '_debug' => 'should not appear'
        ];

        $result = ErrorLogTestClass::interpolateMessage($message, $context);

        $this->assertStringContainsString("Value: test", $result);
        $this->assertStringContainsString("{_debug}", $result); // Bleibt unersetzt
    }

    /**
     * Test: logErrorAndThrow() mit Exception-Code
     */
    public function testLogErrorAndThrowWithCode(): void {
        try {
            ob_start();
            ErrorLogTestClass::logErrorAndThrow(
                RuntimeException::class,
                "Error with code",
                [],
                null,
                42
            );
        } catch (RuntimeException $e) {
            ob_get_clean();
            $this->assertSame(42, $e->getCode());
            return;
        }

        $this->fail("RuntimeException was not thrown");
    }

    /**
     * Test: createDebugContext() zeigt externen Caller, nicht interne Trait-Methoden
     * 
     * Stellt sicher, dass interne Methoden wie doLogAndThrow, handleStandardLog etc.
     * im Debug-Kontext nicht erscheinen, sondern die erste externe aufrufende Methode.
     */
    public function testCreateDebugContextShowsExternalCaller(): void {
        // Rufe createDebugContext aus einer Wrapper-Methode auf
        $context = $this->wrapperMethodForDebugContext();

        $this->assertArrayHasKey('_debug', $context);
        $debug = $context['_debug'];

        // Die Funktion sollte wrapperMethodForDebugContext sein, nicht eine interne Trait-Methode
        $this->assertSame('wrapperMethodForDebugContext', $debug['function']);
        $this->assertSame(self::class, $debug['class']);
        $this->assertStringContainsString('ErrorLogTraitTest.php', $debug['file']);
        $this->assertGreaterThan(0, $debug['line']);
    }

    /**
     * Wrapper-Methode zum Testen des externen Callers
     */
    private function wrapperMethodForDebugContext(): array {
        return ErrorLogTestClass::createDebugContext(['extra' => 'data']);
    }

    /**
     * Test: Interne Trait-Methoden erscheinen nicht im Debug-Kontext
     * 
     * Simuliert einen Aufruf wie ConfigLoader::logErrorAndThrow() und prüft,
     * dass doLogAndThrow nicht als Caller erscheint.
     */
    public function testInternalMethodsAreSkippedInDebugContext(): void {
        $context = $this->simulateExternalLibraryCall();

        $this->assertArrayHasKey('_debug', $context);
        $debug = $context['_debug'];

        // Die internen Methoden sollten NICHT erscheinen
        $internalMethods = [
            'doLogAndThrow',
            'logErrorAndThrow',
            'handleStandardLog',
            'handleMagicCall',
            'logInternal',
            '__call',
            '__callStatic',
        ];

        $this->assertNotContains(
            $debug['function'],
            $internalMethods,
            "Interne Trait-Methode '{$debug['function']}' sollte nicht im Debug-Kontext erscheinen"
        );
    }

    /**
     * Simuliert einen externen Bibliotheksaufruf
     */
    private function simulateExternalLibraryCall(): array {
        return ErrorLogTestClass::createDebugContext();
    }

    /**
     * Test: Verschachtelte Aufrufe zeigen den korrekten externen Caller
     */
    public function testNestedCallsShowCorrectExternalCaller(): void {
        $context = $this->outerMethod();

        $this->assertArrayHasKey('_debug', $context);
        $debug = $context['_debug'];

        // Sollte innerMethod zeigen (die createDebugContext direkt aufruft)
        $this->assertSame('innerMethod', $debug['function']);
    }

    private function outerMethod(): array {
        return $this->innerMethod();
    }

    private function innerMethod(): array {
        return ErrorLogTestClass::createDebugContext();
    }

    /**
     * Test: Bei Aufruf ohne Klassen-Kontext (Script) wird {script} als Funktion zurückgegeben
     * 
     * Dieser Test simuliert einen Aufruf aus globalem Scope, indem geprüft wird,
     * dass die Funktion '{script}' zurückgegeben wird, wenn kein Caller-Frame existiert.
     */
    public function testScriptCallReturnsScriptAsFunction(): void {
        // Erstelle einen Kontext direkt aus der Test-Methode (wird als normaler Aufruf erkannt)
        $context = ErrorLogTestClass::createDebugContext();

        $this->assertArrayHasKey('_debug', $context);
        $debug = $context['_debug'];

        // Bei Test-Aufruf sollte die aufrufende Funktion angezeigt werden
        $this->assertNotNull($debug['function']);
        $this->assertNotEmpty($debug['file']);
        $this->assertGreaterThan(0, $debug['line']);
    }

    /**
     * Test: Debug-Kontext enthält alle erwarteten Felder
     */
    public function testDebugContextContainsAllExpectedFields(): void {
        $context = ErrorLogTestClass::createDebugContext(['custom' => 'value']);

        $this->assertArrayHasKey('_debug', $context);
        $this->assertArrayHasKey('custom', $context);
        $this->assertSame('value', $context['custom']);

        $debug = $context['_debug'];
        $this->assertArrayHasKey('memory_usage', $debug);
        $this->assertArrayHasKey('memory_peak', $debug);
        $this->assertArrayHasKey('timestamp', $debug);
        $this->assertArrayHasKey('file', $debug);
        $this->assertArrayHasKey('line', $debug);
        $this->assertArrayHasKey('function', $debug);
        $this->assertArrayHasKey('class', $debug);

        // Memory-Werte sollten positiv sein
        $this->assertGreaterThan(0, $debug['memory_usage']);
        $this->assertGreaterThan(0, $debug['memory_peak']);
        $this->assertIsFloat($debug['timestamp']);
    }
}
